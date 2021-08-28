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
            $appointmentString .= $messageList[$speciality][$type]["REMINDER"][$arr[0]["language"]]["message"];

            $appListString = array_reduce($arr, function($acc, $y) {
                return match($y["language"]) {
                    "French" => $acc ."$y[name] le $y[date] à $y[time]\n",
                    default  => $acc ."$y[name] on $y[date] at $y[time]\n"
                };
            }, "");

            $appointmentString = preg_replace("/<app>/", $appListString, $appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/", "", $appointmentString); //remove last newline

    $appointmentSerString = array_reduce($x, fn($acc, $y) => [...$acc,$y["appSer"]], []);

    $appointmentNameString = array_reduce($x, fn($acc, $y) => $acc . "$y[fullname],", "");

    if(ArrayUtil::checkIfArrayIsAssoc($x) === false) {
        $x = array_merge(...$x);
    }

    unset($x["date"]);
    unset($x["time"]);
    unset($x["name"]);
    $x["appString"] = $appointmentString;
    $x["appSer"] = $appointmentSerString;
    $x["appName"] = $appointmentNameString;

    return $x;
}, $patients);

//send sms to patient
//also mark each appointment as having a reminder sent already
foreach($patients as $pat)
{
    if($pat["appString"] !== "") {
        SmsInterface::sendSms($pat["phoneNumber"], $pat["appString"]);
    }

    foreach($pat["appSer"] as $x) {
        setAppointmentReminderFlag((int) $x);
    }
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
            AND MV.AppointmentReminderSent = 0
            AND MV.ScheduledDate = CURDATE() + INTERVAL 1 DAY
        ORDER BY
            mrnSite,
            MV.ScheduledTime
    ");
    $query->execute();

    return Encoding::utf8_encode_recursive($query->fetchAll());
}

function setAppointmentReminderFlag(int $appointmentId): void
{
    Database::getOrmsConnection()->prepare("
        UPDATE MediVisitAppointmentList
        SET
            AppointmentReminderSent = 1
        WHERE
            AppointmentSerNum = ?
    ")->execute([$appointmentId]);
}
