<?php declare(strict_types = 1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Sms\SmsInterface;
use Orms\ArrayUtil;

$checkInScriptUrl = Config::getConfigs("path")["BASE_URL"] ."/php/system/checkInPatientAriaMedi.php";

#get all the message that we received since the last time we checked

//find way to get timestamp of last run
$lastRun = getLastRun();
$currentRun = new DateTime();

setLastRun($currentRun);
$messages = SmsInterface::getReceivedMessages($lastRun);

#log all received messages immediately in case the script dies in the middle of processing
foreach($messages as $message)
{
    logMessageData($message->timeReceived,$message->clientNumber,NULL,$message->body,NULL,"SMS received");
}

#filter all messages that were sent over 10 minutes ago
$messages = array_filter($messages,function($message) use($currentRun){
    if($message->timeReceived < $currentRun->modify("-10 minutes")) {
        logMessageData($message->timeReceived,$message->clientNumber,NULL,$message->body,NULL,"SMS expired");
        return 0;
    }

    return 1;
});

#get default messages
$messageList = SmsInterface::getPossibleSmsMessages();
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

        SmsInterface::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$returnString,"Success");

        continue;
    }

    #get the patients next appointments for the day
    $appointments = getAppointmentList($patientSer);

    #if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        SmsInterface::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
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
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$language]["Message"];

            $appListString = array_reduce($apps,function($acc,$x) use($language) {
                if($language === "French") return $acc . "$x[name] à $x[time]\n";
                else return $acc . "$x[name] at $x[time]\n";
            },"");

            $appointmentString = preg_replace("/<app>/",$appListString,$appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/","",$appointmentString); #remove last newline

    #check the patient into all of his appointments
    $checkInRequest = curl_init("$checkInScriptUrl?".http_build_query(["CheckinVenue" => $checkInLocation,"PatientId" => $patientMrn]));
    curl_setopt_array($checkInRequest,[
        CURLOPT_RETURNTRANSFER => TRUE
    ]);
    curl_exec($checkInRequest);
    $checkInResult = curl_getinfo($checkInRequest)["http_code"];

    if($checkInResult < 300)
    {
        SmsInterface::sendSms($message->clientNumber,$appointmentString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$appointmentString,"Success");
    }
    else
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        SmsInterface::sendSms($message->clientNumber,$returnString,$message->serviceNumber);
        logMessageData($message->timeReceived,$message->clientNumber,$patientSer,$message->body,$returnString,"Error");
    }
}

#functions

function getAppointmentList(string $pSer): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
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
    $dbh = Config::getDatabaseConnection("ORMS");
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

function getPatientInfo(string $phoneNumber): ?array
{
    $dbh = Config::getDatabaseConnection("ORMS");
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
    $dbh = Config::getDatabaseConnection("ORMS");
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
    $dbh = Config::getDatabaseConnection("ORMS");
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
