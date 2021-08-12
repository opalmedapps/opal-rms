<?php

declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Appointment\Location;
use Orms\DataAccess\Database;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

//get all the message that we received since the last time we checked
//also check up to 5 minutes before the last run to get any messages that may have been missed

$lastRun = getLastRun();
$currentRun = new DateTime();

setLastRun($currentRun);

$timeLimit = $currentRun->modify("-5 minutes");

$messages = SmsInterface::getNewReceivedMessages($lastRun->modify("-5 minutes"));

//log all received messages immediately in case the script dies in the middle of processing
foreach($messages as $message) {
    logMessageData($message->timeReceived, $message->clientNumber, null, $message->body, null, "SMS received");
}

//filter all messages that were sent over 5 minutes ago, just in case
$messages = array_filter($messages, function($message) use ($timeLimit) {
    if($message->timeReceived < $timeLimit) {
        logMessageData($message->timeReceived, $message->clientNumber, null, $message->body, null, "SMS expired");
        return false;
    }

    return true;
});

//get default messages
$messageList = SmsInterface::getPossibleSmsMessages();
$checkInLocation = "CELL PHONE";

//process messages
foreach($messages as $message)
{
    //find the patient associated with the phone number
    $patientData = getPatientInfo($message->clientNumber);

    //return nothing if the patient doesn't exist and skip
    if($patientData === null)
    {
        logMessageData($message->timeReceived, $message->clientNumber, null, $message->body, null, "Patient not found");
        continue;
    }

    $patientSer = $patientData["PatientSerNum"];
    $language = $patientData["LanguagePreference"];

    //sleep first to make sure the previous text message had time to be sent
    sleep(2);

    //check if the message is equal to the check in keyword ARRIVE
    //if it's not, send a message to the patient instructing them how to use the service
    if(!preg_match("/ARRIVE|ARRIVÉ/", mb_strtoupper($message->body)))
    {
        $returnString = SmsInterface::getDefaultUnknownCommandMessage($language) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        logMessageData($message->timeReceived, $message->clientNumber, $patientSer, $message->body, $returnString, "Success");

        continue;
    }

    //get the patients next appointments for the day
    $appointments = getAppointmentList($patientSer);

    //if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = SmsInterface::getDefaultFailedCheckInMessage($language) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        logMessageData($message->timeReceived, $message->clientNumber, $patientSer, $message->body, $returnString, "Success");

        continue;
    }

    //sort the appointments by speciality and type into a string to insert in the sms message
    //also generate the combined message to send in case the patient has multiple types of appointments
    $appointmentsSorted = ArrayUtil::groupArrayByKeyRecursive($appointments, "SpecialityGroupId", "Type");

    $appointmentString = "";
    foreach($appointmentsSorted as $speciality => $v)
    {
        foreach($v as $type => $apps)
        {
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$language]["Message"] ?? "";

            $appListString = array_reduce($apps, function($acc, $x) use ($language) {
                if($language === "French") return $acc . "$x[name] à $x[time]\n";
                else return $acc . "$x[name] at $x[time]\n";
            }, "");

            $appointmentString = preg_replace("/<app>/", $appListString, $appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = rtrim($appointmentString); //remove trailing newlines
    $appointmentString = preg_replace("/\n\n----------------$/", "", $appointmentString) ?? ""; //remove last separator newline

    //check the patient into all of his appointments
    $patient = PatientInterface::getPatientById((int) $patientSer);

    try
    {
        if($patient === null) throw new Exception("Unknown patient");

        Location::movePatientToLocation($patient, $checkInLocation);

        SmsInterface::sendSms($message->clientNumber, $appointmentString, $message->serviceNumber);
        logMessageData($message->timeReceived, $message->clientNumber, $patientSer, $message->body, $appointmentString, "Success");
    }
    catch(\Exception $e)
    {
        $returnString = SmsInterface::getDefaultUnknownCommandMessage($language) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        logMessageData($message->timeReceived, $message->clientNumber, $patientSer, $message->body, $returnString, "Error: ". $e->getTraceAsString());
    }
}

//functions
/**
 *
 * @return array<string,string>
 * @throws Exception
 * @throws PDOException
 */
function getAppointmentList(string $pSer): array
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        SELECT
            MV.AppointmentSerNum AS id,
            CASE
                WHEN MV.AppointSys = 'Aria' THEN
                    CASE
                        WHEN AC.AppointmentCode LIKE '.EB%' THEN 'Radiotherapy'
                        WHEN (AC.AppointmentCode LIKE 'Consult%'
                            OR AC.AppointmentCode LIKE 'CONSULT%') THEN 'Consult'
                        WHEN AC.AppointmentCode LIKE 'FOLLOW UP %' THEN 'Follow Up'
                        ELSE AC.AppointmentCode
                    END
                ELSE CR.ResourceName
            END AS name,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time,
            SA.SpecialityGroupId,
            SA.Type
        FROM
            MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
            INNER JOIN SmsAppointment SA ON SA.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND SA.AppointmentCodeId = MV.AppointmentCodeId
                AND SA.Active = 1
                AND SA.Type IS NOT NULL
        WHERE
            MV.PatientSerNum = :pSer
            AND MV.ScheduledDate = CURDATE()
            AND MV.Status = 'Open'
        ORDER BY MV.ScheduledTime
    ");
    $query->execute([":pSer" => $pSer]);

    return Encoding::utf8_encode_recursive($query->fetchAll());
}

function logMessageData(DateTime $timestamp, string $phoneNumber, ?string $patientSer, string $message, ?string $returnMessage, string $result): void
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        INSERT INTO TEMP_IncomingSmsLog(ReceivedTimestamp,FromPhoneNumber,PatientSerNum,ReceivedMessage,ReturnMessage,Result)
        VALUES(:rec,:num,:pSer,:msg,:rmsg,:res)
    ");
    $query->execute([
        ":rec" => $timestamp->format("Y:m:d H:i:s"),
        ":num" => $phoneNumber,
        ":pSer" => $patientSer,
        ":msg" => $message,
        ":rmsg" => $returnMessage,
        ":res" => $result
    ]);
}

/**
 *
 * @return null|array<string,string>
 * @throws Exception
 * @throws PDOException
 */
function getPatientInfo(string $phoneNumber): ?array
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        SELECT
            PatientSerNum,
            LanguagePreference
        FROM
            Patient
        WHERE
            SMSAlertNum = :num
        LIMIT 1
    ");
    $query->execute([":num" => $phoneNumber]);

    return $query->fetchAll()[0] ?? null;
}

function getLastRun(): DateTime
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        SELECT
            LastReceivedSmsFetch
        FROM
            Cron
        WHERE
            System = 'ORMS'
    ");
    $query->execute();

    $lastRun = $query->fetchAll()[0]["LastReceivedSmsFetch"] ?? null;
    if($lastRun === null) {
        $lastRun = new DateTime((new DateTime())->format("Y-m-d")); //start of today
    }
    else {
        $lastRun = new DateTime($lastRun);
    }

    return $lastRun;
}

function setLastRun(DateTime $timestamp): void
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        UPDATE Cron
        SET
           LastReceivedSmsFetch = ?
        WHERE
            System = 'ORMS'
    ");
    $query->execute([$timestamp->format("Y-m-d H:i:s")]);
}
