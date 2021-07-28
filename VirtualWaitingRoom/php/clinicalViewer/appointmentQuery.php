<?php declare(strict_types=1);
//---------------------------------------------------------------------------------------------------------------
// This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
//---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;
use Orms\Hospital\Opal;
use Orms\Diagnosis\DiagnosisInterface;

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
    "completed" => isset($_GET["comp"]) ? "'Completed'" : NULL,
    "open"      => isset($_GET["openn"]) ? "'Open'" : NULL,
    "cancelled" => isset($_GET["canc"]) ? "'Cancelled'" : NULL,
];
$activeStatusConditions = array_filter($statusConditions);

$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";
$qDate = "$qDateInit $qTime";

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

$statusFilter = " AND MV.Status IN (". implode(",",$activeStatusConditions) .")";
$appFilter = ($appType === "all") ? "" : " AND CR.ResourceName IN ($specificApp)";
$cappFilter = ($appcType === "all") ? "" : " AND COALESCE(AC.DisplayName,AC.AppointmentCode) IN ($cspecificApp)";

//ORMS database query run under "and" mode for basic appoint information
$dbh = Database::getOrmsConnection();

$queryAppointments = $dbh->prepare("
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
            $statusFilter
            $appFilter
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
            AND SG.SpecialityGroupId = :spec
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
            $cappFilter
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            AND PH.HospitalId = SG.HospitalId
            AND PH.Active = 1
        INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
    ORDER BY
        MV.ScheduledDate,
        MV.ScheduledTime
");

$listOfAppointments = [];
$patientList = [];

//get ORMS patients if the appointment filter is disabled
if($afilter === FALSE)
{
    $queryAppointments->execute([
        ":sDate" => $sDate,
        ":eDate" => $eDate,
        ":spec"  => $speciality
    ]);

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
        $app["QuestionnaireName"] = "";
        $app["QStatus"] = "";
        $recentlyAnswered = NULL;
        $answeredQuestionnaire = FALSE;

        if($app["OpalPatient"] === "1")
        {
            $opalQuestionnaire = Opal::getLastCompletedPatientQuestionnaireClinicalViewer($app["MedicalRecordNumber"],$app["HospitalCode"],$qDate);

            $app["QuestionnaireName"] = $opalQuestionnaire["QuestionnaireName"] ?? "";

            $lastCompleted = $opalQuestionnaire["QuestionnaireCompletionDate"] ?? NULL;
            $recentlyAnswered = $opalQuestionnaire["RecentlyAnswered"] ?? NULL;

            $completedWithinWeek = $opalQuestionnaire["CompletedWithinLastWeek"] ?? NULL;
            $app["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : "";

            if (
                ($lastCompleted !== NULL && $app["LastQuestionnaireReview"] === NULL)
                ||
                (
                    ($lastCompleted !== NULL && $app["LastQuestionnaireReview"] !== NULL)
                    && (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($app["LastQuestionnaireReview"]))->getTimestamp()
                )
            ) $app["QStatus"] = "red-circle";

            //check if any of a patient's questionnaires are in the user selected questionnaire list
            $listOfPatientQuestionnaires = array_column(Opal::getListOfQuestionnairesForPatientClinicalViewer($app["MedicalRecordNumber"],$app["HospitalCode"]),"QuestionnaireName_EN");
            $userSelectedQuestionnaires = explode(",",$qspecificApp);

            $answeredQuestionnaire = (array_intersect($listOfPatientQuestionnaires,$userSelectedQuestionnaires) !== []);
        }

        if(
            ($appdType === "all" || checkDiagnosis((int) $app["PatientSerNum"],explode(",",$dspecificApp)) === TRUE)
            && ($qfilter === TRUE || $offbutton === "OFF" || $andbutton === "Or" || $recentlyAnswered === "1")
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
                "sex"           => substr($app["Sex"],0,1),
                "SMSAlertNum"   => $app["SMSAlertNum"],
                "LastReview"    => $app["LastQuestionnaireReview"],
            ];
        }
    }
}

//if appointment filter is disabled or clinical viewer is under "or" mode
if($andbutton === "Or" || ($qfilter === FALSE && $afilter === TRUE))
{
    $qappFilter = ($qType === "all") ? [] : explode(",",$qspecificApp);

    //we need to know which site to look

    $patients = Opal::getOpalPatientsAccordingToVariousFilters($qappFilter,$qDate);

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
        $recentlyAnswered = $pat["RecentlyAnswered"] ?? NULL;

        if(! (
            in_array($pat["PatientSerNum"] ?? NULL,$patientList) === FALSE
            && ($offbutton === "OFF" || $recentlyAnswered === "1")
            && ($qType === "all" || $pat["QuestionnaireName"] !== NULL)
            && $pat["QuestionnaireCompletionDate"] !== NULL
        )) continue;

        $queryPatientInformation->execute([
            ":mrn"  => $pat["Mrn"],
            ":site" => $pat["Site"]
        ]);
        $ormsInfo = $queryPatientInformation->fetchAll()[0] ?? [];

        if(
            $ormsInfo === []
            || ($appdType === "all" || checkDiagnosis((int) $ormsInfo["PatientSerNum"],explode(",",$dspecificApp)) === TRUE)
        ) continue;

        $completedWithinWeek = $pat["CompletedWithinLastWeek"] ?? NULL;
        $pat["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : "";

        $lastCompleted = $pat["QuestionnaireCompletionDate"] ?? NULL;
        if( /** @phpstan-ignore-next-line */
            ($lastCompleted !== NULL && $ormsInfo["LastQuestionnaireReview"] === NULL)
            || (
                ($lastCompleted !== NULL && $ormsInfo["LastQuestionnaireReview"] !== NULL)
                && (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($ormsInfo["LastQuestionnaireReview"]))->getTimestamp()
            )
        ) $pat["QStatus"] = "red-circle";

        if($ormsInfo["SMSAlertNum"]) $ormsInfo["SMSAlertNum"] = substr($ormsInfo["SMSAlertNum"],0,3) ."-". substr($ormsInfo["SMSAlertNum"],3,3) ."-". substr($ormsInfo["SMSAlertNum"],6,4);

        $listOfAppointments[] = [
            "fname"         => $ormsInfo["FirstName"],
            "lname"         => $ormsInfo["LastName"],
            "mrn"           => $pat["MedicalRecordNumber"],
            "site"          => $pat["HospitalCode"],
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
            "sex"           => substr($ormsInfo["Sex"],0,1),
            "SMSAlertNum"   => $ormsInfo["SMSAlertNum"],
            "LastReview"    => $ormsInfo["LastQuestionnaireReview"],
        ];
    }
}

$listOfAppointments = Encoding::utf8_encode_recursive($listOfAppointments);
echo json_encode($listOfAppointments);

/**
 *
 * @param mixed[] $diagnosisList
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
