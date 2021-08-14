<?php

declare(strict_types=1);
//---------------------------------------------------------------------------------------------------------------
// This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
//---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$sDateInit              = $params["sDate"] ?? null;
$eDateInit              = $params["eDate"] ?? null;
$sTime                  = $params["sTime"] ?? null;
$eTime                  = $params["eTime"] ?? null;
$speciality             = $params["speciality"] ?? null;
$checkForArrived        = isset($params["arrived"]);
$checkForNotArrived     = isset($params["notArrived"]);
$opal                   = isset($params["opal"]);
$sms                    = isset($params["SMS"]);
$appType                = $params["type"] ?? null;
$specificApp            = $params["specificType"] ?? "NULL";
$appcType               = $params["ctype"] ?? null;
$cspecificApp           = $params["cspecificType"] ?? "NULL";
$appdType               = $params["dtype"] ?? null;
$dspecificApp           = $params["dspecificType"] ?? "NULL";
$qType                  = $params["qtype"] ?? null;
$qspecificApp           = $params["qspecificType"] ?? "NULL";
$qDateInit              = $params["qselectedDate"] ?? null;
$qTime                  = $params["qselectedTime"] ?? null;
$offbutton              = $params["offbutton"] ?? null;
$andbutton              = $params["andbutton"] ?? null;
$afilter                = isset($params["afilter"]);
$qfilter                = isset($params["qfilter"]);

$statusConditions = [
    isset($params["comp"]) ? "Completed" : null,
    isset($params["openn"]) ? "Open" : null,
    isset($params["canc"]) ? "Cancelled" : null,
];
$activeStatusConditions = array_filter($statusConditions);

$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";
$qDate = "$qDateInit $qTime:00";

//Set the filter for SMS/OPAL status, based on the parameters.
$opalFilter = "";

if($opal === false && $sms === false) {
    $opalFilter = "AND (P.SMSAlertNum IS NULL OR P.OpalPatient = 0)";
}
elseif($opal === false) {
    $opalFilter .= "AND P.SMSAlertNum IS NOT NULL";
}
elseif($sms === false) {
    $opalFilter .= "AND P.OpalPatient = 1";
}
else {
    $opalFilter = "AND (P.SMSAlertNum IS NOT NULL OR P.OpalPatient = 1)";
}

//ORMS database query run under "and" mode for basic appoint information
$dbh = Database::getOrmsConnection();

$sqlAppointments = "
    SELECT
        MV.AppointmentSerNum,
        P.FirstName,
        P.LastName,
        PH.MedicalRecordNumber,
        H.HospitalCode,
        P.PatientSerNum,
        CR.ResourceName,
        CR.ResourceCode,
        COALESCE(AC.DisplayName,AC.AppointmentCode) AS AppointmentCode,
        MV.Status,
        MV.ScheduledDate,
        TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS ScheduledTime,
        (SELECT PL.ArrivalDateTime FROM PatientLocation PL WHERE PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 LIMIT 1) AS CurrentCheckInTime,
        (SELECT PLM.ArrivalDateTime FROM PatientLocationMH PLM WHERE PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 LIMIT 1) AS PreviousCheckInTime,
        MV.MedivisitStatus,
        (SELECT DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE TEMP_PatientQuestionnaireReview.PatientSer = P.PatientSerNum) AS LastQuestionnaireReview,
        P.OpalPatient,
        TIMESTAMPDIFF(YEAR,P.DateOfBirth,CURDATE()) AS Age,
        P.Sex,
        P.SMSAlertNum
    FROM
        Patient P
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
            AND MV.Status != 'Deleted'
            AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
            $opalFilter
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            AND :statusFilter:
            AND :appointmentFilter:
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
            AND SG.SpecialityGroupId = :spec
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
            AND :codeFilter:
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            AND PH.HospitalId = SG.HospitalId
            AND PH.Active = 1
        INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
    ORDER BY
        MV.ScheduledDate,
        MV.ScheduledTime
";

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointments, ":statusFilter:", "MV.Status", $activeStatusConditions);
$boundValues = $sqlAppointmentsMod["boundValues"];

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointmentsMod["sqlString"], ":appointmentFilter:", "CR.ResourceName", $appType === "all" ? [] : explode("|||", $specificApp));
$boundValues = array_merge($boundValues, $sqlAppointmentsMod["boundValues"]);

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointmentsMod["sqlString"], ":codeFilter:", "COALESCE(AC.DisplayName,AC.AppointmentCode)", $appcType === "all" ? [] : explode("|||", $cspecificApp));
$boundValues = array_merge($boundValues, $sqlAppointmentsMod["boundValues"]);

$queryAppointments = $dbh->prepare($sqlAppointmentsMod["sqlString"]);

$listOfAppointments = [];
$patientList = [];

//get ORMS patients if the appointment filter is disabled
if($afilter === false)
{
    $queryAppointments->execute(array_merge(
        [
            ":sDate" => $sDate,
            ":eDate" => $eDate,
            ":spec"  => $speciality
        ],
        $boundValues
    ));

    foreach($queryAppointments->fetchAll() as $app)
    {
        //filter apppointments on whether the patient checked in for it
        $checkInTime = $app["CurrentCheckInTime"] ?? $app["PreviousCheckInTime"] ?? null;

        if($checkForArrived === true && $checkForNotArrived === true)   $filterAppointment = false;
        elseif($checkForArrived === true && $checkInTime === null)      $filterAppointment = true;
        elseif($checkForNotArrived === true && $checkInTime !== null)   $filterAppointment = true;
        else                                                            $filterAppointment = false;

        if($filterAppointment === true) continue;

        //mark this patient as seen
        if(in_array($app["PatientSerNum"], $patientList) === false) {
            $patientList[] = $app["PatientSerNum"];
        }

        if($app["SMSAlertNum"] !== null) $app["SMSAlertNum"] = mb_substr($app["SMSAlertNum"], 0, 3) ."-". mb_substr($app["SMSAlertNum"], 3, 3) ."-". mb_substr($app["SMSAlertNum"], 6, 4);

        //define opal fields and fill them if the patient is an opal patient
        $app["QStatus"] = null;
        $recentlyAnswered = null;
        $answeredQuestionnaire = false;

        if($app["OpalPatient"] === "1")
        {
            $patient = PatientInterface::getPatientById((int) $app["PatientSerNum"]) ?? throw new Exception("Unknown patient id $app[PatientSerNum]");

            //check the patient's last completed questionnaire
            try {
                $questionnaire = Fetch::getLastCompletedPatientQuestionnaire($patient);
            }
            catch(Exception $e) {
                $questionnaire = null;
                error_log((string) $e);
            }

            if($questionnaire !== null)
            {
                $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i:s", $qDate);
                $recentlyAnswered = $questionnaireDateLimit <= $questionnaire["lastUpdated"];

                $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
                $completedWithinWeek = ($oneWeekAgo <=  $questionnaire["completionDate"]);

                $app["QStatus"] = ($completedWithinWeek === true) ? "green-circle" : null;

                $lastQuestionnaireReview = ($app["LastQuestionnaireReview"] !== null) ? new DateTime($app["LastQuestionnaireReview"]) : null;

                if($lastQuestionnaireReview === null || $questionnaire["completionDate"] > $lastQuestionnaireReview) {
                    $app["QStatus"] = "red-circle";
                }
            }

            //check if any of a patient's questionnaires are in the user selected questionnaire list
            try {
                $patientQuestionnaires = array_column(Fetch::getListOfCompletedQuestionnairesForPatient($patient), "questionnaireId");
            }
            catch(Exception $e) {
                $patientQuestionnaires = [];
                error_log((string) $e);
            }
            $userSelectedQuestionnaires = explode(",", $qspecificApp);
            $answeredQuestionnaire = (array_intersect($patientQuestionnaires, $userSelectedQuestionnaires) !== []);
        }

        if(
            ($appdType === "all" || checkDiagnosis((int) $app["PatientSerNum"], explode(",", $dspecificApp)) === true)
            && ($qfilter === true || $offbutton === "OFF" || $andbutton === "Or" || $recentlyAnswered === true)
            && ($qType === "all" || $answeredQuestionnaire === true)
        ) {
            $listOfAppointments[] = [
                "fname"         => $app["FirstName"],
                "lname"         => $app["LastName"],
                "mrn"           => $app["MedicalRecordNumber"],
                "site"          => $app["HospitalCode"],
                "patientId"     => $app["PatientSerNum"],
                "appName"       => $app["ResourceName"],
                "appClinic"     => $app["ResourceCode"],
                "appType"       => $app["AppointmentCode"],
                "appStatus"     => $app["Status"],
                "appDay"        => $app["ScheduledDate"],
                "appTime"       => $app["ScheduledTime"],
                "checkin"       => $checkInTime,
                "mediStatus"    => $app["MedivisitStatus"],
                "QStatus"       => $app["QStatus"],
                "opalpatient"   => $app["OpalPatient"],
                "age"           => $app["Age"],
                "sex"           => mb_substr($app["Sex"] ?? "", 0, 1),
                "SMSAlertNum"   => $app["SMSAlertNum"],
                "LastReview"    => $app["LastQuestionnaireReview"] ?? null,
            ];
        }
    }
}

//if appointment filter is disabled or clinical viewer is under "or" mode
if($andbutton === "Or" || ($qfilter === false && $afilter === true))
{
    $qappFilter = ($qType === "all") ? [] : explode(",", $qspecificApp);
    $qappFilter = array_map(fn($x) => (int) $x, $qappFilter);

    $patients = Fetch::getPatientsWhoCompletedQuestionnaires($qappFilter);

    // CASE
    // WHEN Q.LastUpdated BETWEEN :qDate AND NOW() THEN 1
    //     ELSE 0
    // END AS RecentlyAnswered

    $queryPatientInformation = $dbh->prepare("
        SELECT
            P.FirstName,
            P.LastName,
            PH.MedicalRecordNumber,
            H.HospitalCode,
            P.PatientSerNum,
            P.OpalPatient,
            TIMESTAMPDIFF(YEAR,P.DateOfBirth,CURDATE()) AS Age,
            P.Sex,
            P.SMSAlertNum,
            (
                SELECT
                    DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i')
                FROM
                    TEMP_PatientQuestionnaireReview
                WHERE
                    TEMP_PatientQuestionnaireReview.PatientSer = P.PatientSerNum
            ) AS LastQuestionnaireReview
        FROM
            Patient P
            INNER JOIN Hospital H ON H.HospitalCode = :site
            INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                AND PH.HospitalId = H.HospitalId
                AND PH.Active = 1
                AND PH.MedicalRecordNumber = :mrn
    ");

    foreach($patients as $pat)
    {
        //filter as many patients as possible before doing any processing

        $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i:s", $qDate);
        $recentlyAnswered = $questionnaireDateLimit <= $pat["lastUpdated"];

        if(! (
            in_array($pat["PatientSerNum"] ?? null, $patientList) === false
            && ($offbutton === "OFF" || $recentlyAnswered === true)
        )) continue;

        $queryPatientInformation->execute([
            ":mrn"  => $pat["mrn"],
            ":site" => $pat["site"]
        ]);
        $ormsInfo = $queryPatientInformation->fetchAll()[0] ?? [];

        if(
            $ormsInfo === []
            || ($appdType === "all" || checkDiagnosis((int) $ormsInfo["PatientSerNum"], explode(",", $dspecificApp)) === true)
        ) continue;


        $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
        $completedWithinWeek = ($oneWeekAgo <=  $pat["completionDate"]);

        $pat["QStatus"] = ($completedWithinWeek === true) ? "green-circle" : null;

        /** @phpstan-ignore-next-line */
        $lastQuestionnaireReview = ($ormsInfo["LastQuestionnaireReview"] !== null) ? new DateTime($ormsInfo["LastQuestionnaireReview"]) : null;

        /** @phpstan-ignore-next-line */
        if($lastQuestionnaireReview === null || $pat["completionDate"] > $lastQuestionnaireReview) {
            $pat["QStatus"] = "red-circle";
        }

        if($ormsInfo["SMSAlertNum"]) $ormsInfo["SMSAlertNum"] = mb_substr($ormsInfo["SMSAlertNum"], 0, 3) ."-". mb_substr($ormsInfo["SMSAlertNum"], 3, 3) ."-". mb_substr($ormsInfo["SMSAlertNum"], 6, 4);

        $listOfAppointments[] = [
            "fname"         => $ormsInfo["FirstName"],
            "lname"         => $ormsInfo["LastName"],
            "mrn"           => $pat["mrn"],
            "site"          => $pat["site"],
            "patientId"     => $ormsInfo["PatientSerNum"],
            "appName"       => null,
            "appClinic"     => null,
            "appType"       => null,
            "appStatus"     => null,
            "appDay"        => null,
            "appTime"       => null,
            "checkin"       => null,
            "mediStatus"    => null,
            "QStatus"       => $pat["QStatus"],
            "opalpatient"   => $ormsInfo["OpalPatient"],
            "age"           => $ormsInfo["Age"],
            "sex"           => mb_substr($ormsInfo["Sex"] ?? "", 0, 1),
            "SMSAlertNum"   => $ormsInfo["SMSAlertNum"],
            "LastReview"    => $ormsInfo["LastQuestionnaireReview"],
        ];
    }
}

$listOfAppointments = Encoding::utf8_encode_recursive($listOfAppointments);
Http::generateResponseJsonAndExit(200,data: $listOfAppointments);

/**
 *
 * @param string[] $diagnosisList
 * @phpstan-ignore-next-line
 */
function checkDiagnosis(int $patientId, array $diagnosisList): bool
{
    $patientDiagnosis = DiagnosisInterface::getDiagnosisListForPatient($patientId);
    foreach($patientDiagnosis as $d) {
        if(in_array($d->diagnosis->subcode, $diagnosisList)) {
            return true;
        }
    }
    return false;
}
