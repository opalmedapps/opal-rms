<?php declare(strict_types = 1);

require __DIR__."/../../vendor/autoload.php";

use GuzzleHttp\Client;

use Orms\Config;
use Orms\Database;
use Orms\Sms;
use Orms\ArrayUtil;

$checkInScriptUrl = Config::getApplicationSettings()->environment->baseUrl ."/php/system/checkInPatientAriaMedi.php";

//get all the message that we received since the last time we checked
//also check up to 5 minutes before the last run to get any messages that may have been missed

$lastRun = getLastRun();
$currentRun = new DateTime();

setLastRun($currentRun);

$timeLimit = $currentRun->modify("-5 minutes");

$messages = Sms::getNewReceivedMessages($lastRun->modify("-5 minutes"));

#log all received messages immediately in case the script dies in the middle of processing
foreach($messages as $message) {
    logMessageData($message->timeReceived,$message->clientNumber,NULL,$message->body,NULL,"SMS received");
}

#filter all messages that were sent over 5 minutes ago, just in case
$messages = array_filter($messages,function($message) use($timeLimit) {
    if($message->timeReceived < $timeLimit) {
        logMessageData($message->timeReceived,$message->clientNumber,NULL,$message->body,NULL,"SMS expired");
        return FALSE;
    }

    return TRUE;
});

#get default messages
$messageList = Sms::getPossibleSmsMessages();
$checkInLocation = "CELL PHONE";

#process messages
foreach($messages as $message)
{
    #find the patient associated with the phone number
    $patientData = getPatientInfo($message->clientNumber);

    #return nothing if the patient doesn't exist and skip
    if($patientData === NULL)
    {
        logMessageData($message->timeReceived,$message->clientNumber,NULL,$message->body,NULL,"Patient not found");
        continue;
    }

    $patientSer = $patientData["PatientSerNum"];
    $patientMrn = $patientData["PatientId"];
    $language = $patientData["LanguagePreference"];

    #sleep first to make sure the previous text message had time to be sent
    sleep(2);

    #check if the message is equal to the check in keyword ARRIVE
    #if it's not, send a message to the patient instructing them how to use the service
    if(!preg_match("/ARRIVE|ARRIVÉ/",mb_strtoupper($message->body)))
    {
        $returnString = $messageList["Any"]["GENERAL"]["UNKNOWN_COMMAND"][$language]["Message"];

        Sms::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$returnString,"Success");

        continue;
    }

    #get the patients next appointments for the day
    $appointments = getAppointmentList($patientSer);

    #if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        Sms::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$returnString,"Success");

        continue;
    }

    #sort the appointments by speciality and type into a string to insert in the sms message
    #also generate the combined message to send in case the patient has multiple types of appointments
    $appointmentsSorted = ArrayUtil::groupArrayByKeyRecursive($appointments,"Speciality","Type");

    $appointmentString = "";
    foreach($appointmentsSorted as $speciality => $v)
    {
        foreach($v as $type => $apps)
        {
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$language]["Message"] ?? "";

            $appListString = array_reduce($apps,function($acc,$x) use($language) {
                if($language === "French") return $acc . "$x[name] à $x[time]\n";
                else return $acc . "$x[name] at $x[time]\n";
            },"");

            $appointmentString = preg_replace("/<app>/",$appListString,$appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = rtrim($appointmentString); #remove trailing newlines
    $appointmentString = preg_replace("/\n\n----------------$/","",$appointmentString) ?? ""; #remove last separator newline

    #check the patient into all of his appointments
    $client = new Client();
    $checkInResult = $client->request("GET",$checkInScriptUrl,[
        "query" => [
            "CheckinVenue" => $checkInLocation,
            "PatientId"    => $patientMrn
        ]
    ])->getStatusCode();

    if($checkInResult < 300)
    {
        Sms::sendSms($message->clientNumber,$appointmentString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$appointmentString,"Success");
    }
    else
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        Sms::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$returnString,"Error");
    }
}

#functions
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
                        WHEN MV.AppointmentCode LIKE '.EB%' THEN 'Radiotherapy'
                        WHEN (MV.AppointmentCode LIKE 'Consult%'
                            OR MV.AppointmentCode LIKE 'CONSULT%') THEN 'Consult'
                        WHEN MV.AppointmentCode LIKE 'FOLLOW UP %' THEN 'Follow Up'
                        ELSE MV.AppointmentCode
                    END
                ELSE MV.ResourceDescription
            END AS name,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time,
            SA.Speciality,
            SA.Type
        FROM
            MediVisitAppointmentList MV
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

    return utf8_encode_recursive($query->fetchAll());
}

function logMessageData(DateTime $timestamp,string $phoneNumber,?string $patientSer,string $message,?string $returnMessage,string $result): void
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
            Patient.PatientSerNum,
            Patient.PatientId,
            Patient.LanguagePreference
        FROM
            Patient
        WHERE
            Patient.SMSAlertNum = :num
        LIMIT 1
    ");
    $query->execute([":num" => $phoneNumber]);

    return $query->fetchAll()[0] ?? NULL;
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

    $lastRun = $query->fetchAll()[0]["LastReceivedSmsFetch"] ?? NULL;
    if($lastRun === NULL) {
        $lastRun = new DateTime((new DateTime())->format("Y-m-d")); #start of today
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

?>
