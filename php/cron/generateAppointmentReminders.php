<?php

declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Sms\SmsInterface;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

//get the list of appointments to process
//group appointments by patient
$appointments = getAppointments();
$patients = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($appointments, "mrnSite");

$messageList = SmsInterface::getPossibleSmsMessages();

//merge appointment entries for the same patient
//combine all appointments a patient has into a string with the appropriate messages(s)
$patients = array_map(function($x) use ($messageList) {

    $appts = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($x, "speciality", "type");

    $appointmentString = "";
    foreach($appts as $speciality => $v)
    {
        foreach($v as $type => $arr)
        {
            $appointmentString .= $messageList[$speciality][$type]["REMINDER"][$arr[0]["language"]]["Message"];

            $appListString = array_reduce($arr, function($acc, $y) {
                if($y["language"] === "French") return $acc . "$y[name] le $y[date] à $y[time]\n";
                else return $acc . "$y[name] on $y[date] at $y[time]\n";
            }, "");

            $appointmentString = preg_replace("/<app>/", $appListString, $appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/", "", $appointmentString); //remove last newline

    $appointmentSerString = array_reduce($x, fn($acc, $y) => $acc . "$y[appSer],", "");

    $appointmentNameString = array_reduce($x, fn($acc, $y) => $acc . "$y[fullname],", "");

    if(ArrayUtil::checkIfArrayIsAssoc($x) === false) $x = array_merge(...$x);

    unset($x["date"]);
    unset($x["time"]);
    unset($x["name"]);
    $x["appString"] = $appointmentString;
    $x["appSer"] = $appointmentSerString;
    $x["appName"] = $appointmentNameString;

    return $x;
}, $patients);

//send sms to patient
foreach($patients as $pat)
{
    if($pat["appString"] !== "") {
        SmsInterface::sendSms($pat["phoneNumber"], $pat["appString"]);
    }
    logReminderData($pat["mrnSite"], $pat["phoneNumber"], $pat["appString"], $pat["appSer"], $pat["appName"]);
}


//functions

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
            CONCAT(PH.MedicalRecordNumber,'-',H.HospitalCode) AS mrnSite,
            P.SMSAlertNum AS phoneNumber,
            P.LanguagePreference AS language,
            MV.AppointmentSerNum AS appSer,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time,
            CR.ResourceName AS fullname,
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
            SA.SpecialityGroupId AS speciality,
            SA.Type as type
        FROM
            MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
            INNER JOIN SmsAppointment SA ON SA.AppointmentCodeId = MV.AppointmentCodeId
                AND SA.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND SA.Active = 1
                AND SA.Type IS NOT NULL
            INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
                AND P.SMSAlertNum != ''
                AND P.SMSAlertNum IS NOT NULL
            INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
            INNER JOIN SpecialityGroup SG ON SG.HospitalId = H.HospitalId
                AND SG.SpecialityGroupId = SA.SpecialityGroupId
        WHERE
            MV.Status = 'Open'
            AND MV.ScheduledDate = CURDATE() + INTERVAL 1 DAY
        ORDER BY
            mrnSite,
            MV.ScheduledTime
    ");
    $query->execute();

    //filter if a reminder was already sent for this appointment
    $appointments = array_filter($query->fetchAll(), fn($x) => checkIfReminderAlreadySent($x["appSer"]));

    return Encoding::utf8_encode_recursive($appointments);
}

//checks to see if a reminder was already sent for a specific appointment
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

     return ($query->fetchAll()[0]["SmsReminderLogSer"] ?? false) ? false : true;
}

function logReminderData(string $mrnSite, string $phoneNumber, string $message, string $appSer, string $appName): void
{
    $dbh = Database::getOrmsConnection();
    $query = $dbh->prepare("
        INSERT INTO TEMP_SmsReminderLog(Mrn,PhoneNumber,MessageSent,AppointmentSer,AppointmentName)
        VALUES(?,?,?,?,?)
    ");
    $query->execute([$mrnSite,$phoneNumber,$message,$appSer,$appName]);
}
