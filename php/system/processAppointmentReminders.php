<?php declare(strict_types = 1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Util\ArrayUtil;
use Orms\Database;
use Orms\Sms;

#get the list of appointments to process
#group appointments by patient
$appointments = getAppointments();
$patients = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($appointments,"mrn");

$messageList = Sms::getPossibleSmsMessages();

#merge appointment entries for the same patient
#combine all appointments a patient has into a string with the appropriate messages(s)
$patients = array_map(function($x) use ($messageList) {

    $appts = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($x,"speciality","type");

    $appointmentString = "";
    foreach($appts as $speciality => $v)
    {
        foreach($v as $type => $arr)
        {
            $appointmentString .= $messageList[$speciality][$type]["REMINDER"][$arr[0]["language"]]["Message"];

            $appListString = array_reduce($arr,function($acc,$y) {
                if($y["language"] === "French") return $acc . "$y[name] le $y[date] à $y[time]\n";
                else return $acc . "$y[name] on $y[date] at $y[time]\n";
            },"");

            $appointmentString = preg_replace("/<app>/",$appListString,$appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/","",$appointmentString); #remove last newline

    $appointmentSerString = array_reduce($x,function($acc,$y) {
        return $acc . "$y[appSer],";
    },"");

    $appointmentNameString = array_reduce($x,function($acc,$y) {
        return $acc . "$y[fullname],";
    },"");

    if(ArrayUtil::checkIfArrayIsAssoc($x) === FALSE) $x = array_merge(...$x);

    unset($x["date"]);
    unset($x["time"]);
    unset($x["name"]);
    $x["appString"] = $appointmentString;
    $x["appSer"] = $appointmentSerString;
    $x["appName"] = $appointmentNameString;

    return $x;
},$patients);

#send sms to patient
foreach($patients as $pat)
{
    if($pat["appString"] !== "") {
        Sms::sendSms($pat["phoneNumber"],$pat["appString"]);
    }
    logReminderData($pat["mrn"],$pat["phoneNumber"],$pat["appString"],$pat["appSer"],$pat["appName"]);
    #sleep first to make sure the previous text message had time to be sent
    sleep(2);
}


#functions

/**
 *
 * @return array<string,string>
 * @throws Exception
 * @throws PDOException
 */
function getAppointments(): array
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        SELECT
            P.PatientId AS mrn,
            P.SMSAlertNum AS phoneNumber,
            P.LanguagePreference AS language,
            MV.AppointmentSerNum AS appSer,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time,
            MV.ResourceDescription AS fullname,
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
            SA.Speciality AS speciality,
            SA.Type as type
        FROM
            MediVisitAppointmentList MV
            INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
                AND P.SMSAlertNum != ''
                AND P.SMSAlertNum IS NOT NULL
            INNER JOIN SmsAppointment SA ON SA.AppointmentCodeId = MV.AppointmentCodeId
                AND SA.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND SA.Active = 1
                AND SA.Type IS NOT NULL
        WHERE
            MV.Status = 'Open'
            AND MV.ScheduledDate = CURDATE() + INTERVAL 1 DAY
        ORDER BY
            P.PatientId,
            MV.ScheduledTime
    ");
    $query->execute();

    #filter if a reminder was already sent for this appointment
    $appointments = array_filter($query->fetchAll(),function($x) {
        return checkIfReminderAlreadySent($x["appSer"]);
    });

    return Encoding::utf8_encode_recursive($appointments);
}

#checks to see if a reminder was already sent for a specific appointment
function checkIfReminderAlreadySent(string $appSer): bool
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        SELECT SmsReminderLogSer
        FROM TEMP_SmsReminderLog
        WHERE AppointmentSer LIKE :appSer
        LIMIT 1
    ");
    $query->execute([":appSer" => "%$appSer%"]);

     return ($query->fetchAll()[0]["SmsReminderLogSer"] ?? FALSE) ? FALSE : TRUE;
}

function logReminderData(string $mrn,string $phoneNumber,string $message,string $appSer,string $appName): void
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        INSERT INTO TEMP_SmsReminderLog(Mrn,PhoneNumber,MessageSent,AppointmentSer,AppointmentName)
        VALUES(?,?,?,?,?)
    ");
    $query->execute([$mrn,$phoneNumber,$message,$appSer,$appName]);
}
