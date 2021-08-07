<?php declare(strict_types=1);
//---------------------------------------------------------------------------------------------------------------
// This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
//---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DateTime;
use Orms\DataAccess\Database;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Fetch;
use Orms\Patient\PatientInterface;

$sDateInit              = $_GET["sDate"] ?? NULL;
$eDateInit              = $_GET["eDate"] ?? NULL;
$sTime                  = $_GET["sTime"] ?? NULL;
$eTime                  = $_GET["eTime"] ?? NULL;
$speciality             = $_GET["speciality"] ?? NULL;
$checkForArrived        = isset($_GET["arrived"]);
$checkForNotArrived     = isset($_GET["notArrived"]);
$opal                   = isset($_GET["opal"]);
$sms                    = isset($_GET["SMS"]);
$appType                = $_GET["type"] ?? NULL;
$specificApp            = $_GET["specificType"] ?? "NULL";
$appcType               = $_GET["ctype"] ?? NULL;
$cspecificApp           = $_GET["cspecificType"] ?? "NULL";
$appdType               = $_GET["dtype"] ?? NULL;
$dspecificApp           = $_GET["dspecificType"] ?? "NULL";
$qType                  = $_GET["qtype"] ?? NULL;
$qspecificApp           = $_GET["qspecificType"] ?? "NULL";
$qDateInit              = $_GET["qselectedDate"] ?? NULL;
$qTime                  = $_GET["qselectedTime"] ?? NULL;
$offbutton              = $_GET["offbutton"] ?? NULL;
$andbutton              = $_GET["andbutton"] ?? NULL;
$afilter                = isset($_GET["afilter"]);
$qfilter                = isset($_GET["qfilter"]);

$statusConditions = [
    isset($_GET["comp"]) ? "Completed" : NULL,
    isset($_GET["openn"]) ? "Open" : NULL,
    isset($_GET["canc"]) ? "Cancelled" : NULL,
];
$activeStatusConditions = array_filter($statusConditions);

$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";
$qDate = "$qDateInit $qTime:00";

//Set the filter for SMS/OPAL status, based on the parameters.
$opalFilter = "";

if($opal === FALSE && $sms === FALSE) {
    $opalFilter = "AND (P.SMSAlertNum IS NULL OR P.OpalPatient = 0)";
}
elseif($opal === FALSE) {
    $opalFilter .= "AND P.SMSAlertNum IS NOT NULL";
}
elseif($sms === FALSE) {
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

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointments,":statusFilter:","MV.Status",$activeStatusConditions);
$boundValues = $sqlAppointmentsMod["boundValues"];

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointmentsMod["sqlString"],":appointmentFilter:","CR.ResourceName",$appType === "all" ? [] : explode("|||",$specificApp));
$boundValues = array_merge($boundValues,$sqlAppointmentsMod["boundValues"]);

$sqlAppointmentsMod = Database::generateBoundedSqlString($sqlAppointmentsMod["sqlString"],":codeFilter:","COALESCE(AC.DisplayName,AC.AppointmentCode)",$appcType === "all" ? [] : explode("|||",$cspecificApp));
$boundValues = array_merge($boundValues,$sqlAppointmentsMod["boundValues"]);

$queryAppointments = $dbh->prepare($sqlAppointmentsMod["sqlString"]);

$listOfAppointments = [];
$patientList = [];

//get ORMS patients if the appointment filter is disabled
if($afilter === FALSE)
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
        $checkInTime = $app["CurrentCheckInTime"] ?? $app["PreviousCheckInTime"] ?? NULL;

        if($checkForArrived === TRUE && $checkForNotArrived === TRUE)   $filterAppointment = FALSE;
        elseif($checkForArrived === TRUE && $checkInTime === NULL)      $filterAppointment = TRUE;
        elseif($checkForNotArrived === TRUE && $checkInTime !== NULL)   $filterAppointment = TRUE;
        else                                                            $filterAppointment = FALSE;

        if($filterAppointment === TRUE) continue;

        //mark this patient as seen
        if(in_array($app["PatientSerNum"],$patientList) === FALSE) {
            $patientList[] = $app["PatientSerNum"];
        }

        if($app["SMSAlertNum"] !== NULL) $app["SMSAlertNum"] = substr($app["SMSAlertNum"],0,3) ."-". substr($app["SMSAlertNum"],3,3) ."-". substr($app["SMSAlertNum"],6,4);

        //define opal fields and fill them if the patient is an opal patient
        $app["QStatus"] = NULL;
        $recentlyAnswered = NULL;
        $answeredQuestionnaire = FALSE;

        if($app["OpalPatient"] === "1")
        {
            $patient = PatientInterface::getPatientById((int) $app["PatientSerNum"]) ?? throw new Exception("Unknown patient id $app[PatientSerNum]");

            //check the patient's last completed questionnaire
            try {
                $questionnaire = Fetch::getLastCompletedPatientQuestionnaire($patient);
            }
            catch(Exception $e) {
                $questionnaire = NULL;
                error_log((string) $e);
            }

            if($questionnaire !== NULL)
            {
                $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i:s",$qDate);
                $recentlyAnswered = $questionnaireDateLimit <= $questionnaire["lastUpdated"];

                $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
                $completedWithinWeek = ($oneWeekAgo <=  $questionnaire["completionDate"]);

                $app["QStatus"] = ($completedWithinWeek === TRUE) ? "green-circle" : NULL;

                $lastQuestionnaireReview = ($app["LastQuestionnaireReview"] !== NULL) ? new DateTime($app["LastQuestionnaireReview"]) : NULL;

                if($lastQuestionnaireReview === NULL || $questionnaire["completionDate"] > $lastQuestionnaireReview) {
                    $app["QStatus"] = "red-circle";
                }
            }

            //check if any of a patient's questionnaires are in the user selected questionnaire list
            try {
                $patientQuestionnaires = array_column(Fetch::getListOfCompletedQuestionnairesForPatient($patient),"questionnaireId");
            }
            catch(Exception $e) {
                $patientQuestionnaires = [];
                error_log((string) $e);
            }
            $userSelectedQuestionnaires = explode(",",$qspecificApp);
            $answeredQuestionnaire = (array_intersect($patientQuestionnaires,$userSelectedQuestionnaires) !== []);
        }

        if(
            ($appdType === "all" || checkDiagnosis((int) $app["PatientSerNum"],explode(",",$dspecificApp)) === TRUE)
            && ($qfilter === TRUE || $offbutton === "OFF" || $andbutton === "Or" || $recentlyAnswered === TRUE)
            && ($qType === "all" || $answeredQuestionnaire === TRUE)
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
                "sex"           => substr($app["Sex"] ?? "",0,1),
                "SMSAlertNum"   => $app["SMSAlertNum"],
                "LastReview"    => $app["LastQuestionnaireReview"] ?? NULL,
            ];
        }
    }
}

//if appointment filter is disabled or clinical viewer is under "or" mode
if($andbutton === "Or" || ($qfilter === FALSE && $afilter === TRUE))
{
    $qappFilter = ($qType === "all") ? [] : explode(",",$qspecificApp);
    $qappFilter = array_map(fn($x) => (int) $x,$qappFilter);

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

        $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i:s",$qDate);
        $recentlyAnswered = $questionnaireDateLimit <= $pat["lastUpdated"];

        if(! (
            in_array($pat["PatientSerNum"] ?? NULL,$patientList) === FALSE
            && ($offbutton === "OFF" || $recentlyAnswered === TRUE)
        )) continue;

        $queryPatientInformation->execute([
            ":mrn"  => $pat["mrn"],
            ":site" => $pat["site"]
        ]);
        $ormsInfo = $queryPatientInformation->fetchAll()[0] ?? [];

        if(
            $ormsInfo === []
            || ($appdType === "all" || checkDiagnosis((int) $ormsInfo["PatientSerNum"],explode(",",$dspecificApp)) === TRUE)
        ) continue;


        $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
        $completedWithinWeek = ($oneWeekAgo <=  $pat["completionDate"]);

        $pat["QStatus"] = ($completedWithinWeek === TRUE) ? "green-circle" : NULL;

        /** @phpstan-ignore-next-line */
        $lastQuestionnaireReview = ($ormsInfo["LastQuestionnaireReview"] !== NULL) ? new DateTime($ormsInfo["LastQuestionnaireReview"]) : NULL;

        /** @phpstan-ignore-next-line */
        if($lastQuestionnaireReview === NULL || $pat["completionDate"] > $lastQuestionnaireReview) {
            $pat["QStatus"] = "red-circle";
        }

        if($ormsInfo["SMSAlertNum"]) $ormsInfo["SMSAlertNum"] = substr($ormsInfo["SMSAlertNum"],0,3) ."-". substr($ormsInfo["SMSAlertNum"],3,3) ."-". substr($ormsInfo["SMSAlertNum"],6,4);

        $listOfAppointments[] = [
            "fname"         => $ormsInfo["FirstName"],
            "lname"         => $ormsInfo["LastName"],
            "mrn"           => $pat["mrn"],
            "site"          => $pat["site"],
            "patientId"     => $ormsInfo["PatientSerNum"],
            "appName"       => NULL,
            "appClinic"     => NULL,
            "appType"       => NULL,
            "appStatus"     => NULL,
            "appDay"        => NULL,
            "appTime"       => NULL,
            "checkin"       => NULL,
            "mediStatus"    => NULL,
            "QStatus"       => $pat["QStatus"],
            "opalpatient"   => $ormsInfo["OpalPatient"],
            "age"           => $ormsInfo["Age"],
            "sex"           => substr($ormsInfo["Sex"] ?? "",0,1),
            "SMSAlertNum"   => $ormsInfo["SMSAlertNum"],
            "LastReview"    => $ormsInfo["LastQuestionnaireReview"],
        ];
    }
}

$listOfAppointments = Encoding::utf8_encode_recursive($listOfAppointments);
echo json_encode($listOfAppointments);

/**
 *
 * @param string[] $diagnosisList
 */
function checkDiagnosis(int $patientId,array $diagnosisList): bool
{
    $patientDiagnosis = DiagnosisInterface::getDiagnosisListForPatient($patientId);
    foreach($patientDiagnosis as $d) {
        if(in_array($d->diagnosis->subcode,$diagnosisList)) {
            return TRUE;
        }
    }
    return FALSE;
}
