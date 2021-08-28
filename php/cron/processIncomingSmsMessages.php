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

//filter all messages that were sent over 5 minutes ago, just in case
$messages = array_filter($messages, fn($x) => $x->timeReceived >= $timeLimit);

//get default messages
$messageList = SmsInterface::getPossibleSmsMessages();
$checkInLocation = "CELL PHONE";

//process messages
foreach($messages as $message)
{
    //find the patient associated with the phone number
    $patient = PatientInterface::getPatientByPhoneNumber($message->clientNumber);

    //return nothing if the patient doesn't exist and skip
    //also skip if there is no language preference (which should never happen if there is a registered phone number...)
    if($patient === null || $patient->languagePreference === null) {
        continue;
    }

    //check if the message is equal to the check in keyword ARRIVE
    //if it's not, send a message to the patient instructing them how to use the service
    if(!preg_match("/ARRIVE|ARRIVÉ/", mb_strtoupper($message->body)))
    {
        $returnString = SmsInterface::getDefaultUnknownCommandMessage($patient->languagePreference) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        continue;
    }

    //get the patients next appointments for the day
    $appointments = getAppointmentList($patient->id);

    //if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = SmsInterface::getDefaultFailedCheckInMessage($patient->languagePreference) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
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
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$patient->languagePreference]["Message"] ?? "";

            $appListString = array_reduce($apps, function($acc, $x) use ($patient) {
                $preposition = ($patient->languagePreference === "French") ? "à" : "at";

                return $acc ."$x[name] $preposition $x[time]\n";
            }, "");

            $appointmentString = preg_replace("/<app>/", $appListString, $appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = rtrim($appointmentString); //remove trailing newlines
    $appointmentString = preg_replace("/\n\n----------------$/", "", $appointmentString) ?? ""; //remove last separator newline

    //check the patient into all of his appointments
    Location::movePatientToLocation($patient, $checkInLocation);
    SmsInterface::sendSms($message->clientNumber, $appointmentString, $message->serviceNumber);
}

//functions
/**
 *
 * @return array<string,string>
 */
function getAppointmentList(int $patientId): array
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
            MV.PatientSerNum = :pid
            AND MV.ScheduledDate = CURDATE()
            AND MV.Status = 'Open'
        ORDER BY MV.ScheduledTime
    ");
    $query->execute([":pid" => $patientId]);

    return Encoding::utf8_encode_recursive($query->fetchAll());
}

function getLastRun(): DateTime
{
    $query = Database::getOrmsConnection()->prepare("
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
    $query = Database::getOrmsConnection()->prepare("
        UPDATE Cron
        SET
           LastReceivedSmsFetch = ?
        WHERE
            System = 'ORMS'
    ");
    $query->execute([$timestamp->format("Y-m-d H:i:s")]);
}
