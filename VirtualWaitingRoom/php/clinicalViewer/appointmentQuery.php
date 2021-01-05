<?php
declare(strict_types=1);
#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
#---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

#get input parameters

$sDateInit = $_GET["sDate"] ?? NULL;
$eDateInit = $_GET["eDate"] ?? NULL;
$sTime = $_GET["sTime"] ?? NULL;
$eTime = $_GET["eTime"] ?? NULL;
$clinic = $_GET["clinic"] ?? NULL;
$statusConditions = [
    "completed" => isset($_GET["comp"]) ? "'Completed'" : NULL,
    "open" => isset($_GET["openn"]) ? "'Open'" : NULL,
    "cancelled" => isset($_GET["canc"]) ? "'Cancelled'" : NULL,
];

$checkForArrived = isset($_GET["arrived"]) ? TRUE : NULL;
$checkForNotArrived = isset($_GET["notArrived"]) ? TRUE : NULL;
$OPAL = isset($_GET["opal"]) ? TRUE : NULL;
$SMS = isset($_GET["SMS"]) ? TRUE : NULL;
$appType = $_GET["type"] ?? NULL;
$specificApp = $_GET["specificType"] ?? "NULL";
$appcType = $_GET["ctype"] ?? NULL;
$cspecificApp = $_GET["cspecificType"] ?? "NULL";
$appdType = $_GET["dtype"] ?? NULL;
$dspecificApp = $_GET["dspecificType"] ?? "NULL";
$checkRamqExpiration = isset($_GET["checkRamqExpiration"]) ? TRUE : NULL;
$qType = $_GET["qtype"] ?? NULL;
$qspecificApp = $_GET["qspecificType"] ?? "NULL";
$qDateInit = $_GET["qselectedDate"] ?? NULL;
$qTime = $_GET["qselectedTime"] ?? NULL;
$offbutton = $_GET["offbutton"]??NULL;
$andbutton = $_GET["andbutton"]??NULL;
$afilter = isset($_GET["afilter"]) ? TRUE : NULL;
$qfilter = isset($_GET["qfilter"]) ? TRUE : NULL;



$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";
$qDate = "$qDateInit $qTime";


#database connection
$dbh = Config::getDatabaseConnection("ORMS");
$dbOpal = Config::getDatabaseConnection("OPAL");

#opal database query run under 'and' mode
$sqlOpal = "
    SELECT
            DT.Name_EN,
            Q.QuestionnaireCompletionDate,
            Q.CompletedWithinLastWeek,
            Q.RecentAnswered
        FROM
            (SELECT P.PatientId, D.DiagnosisCode
             from Patient P
             INNER JOIN Diagnosis D ON D.PatientSerNum = P.PatientSerNum
             AND P.PatientId = :uid
             ORDER BY D.LastUpdated DESC
             LIMIT 1) DP
             INNER JOIN DiagnosisCode DC ON DC.DiagnosisCode = DP.DiagnosisCode
             INNER JOIN  DiagnosisTranslation DT ON DC.DiagnosisTranslationSerNum = DT.DiagnosisTranslationSerNum,
            (SELECT
                Questionnaire.CompletionDate AS QuestionnaireCompletionDate,
                CASE
                    WHEN Questionnaire.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW() THEN 1
                        ELSE 0
                    END AS CompletedWithinLastWeek,
                CASE
                    WHEN Questionnaire.LastUpdated BETWEEN :qDate AND NOW() THEN 1
                        ELSE 0
                    END AS RecentAnswered
            FROM
                Patient
                INNER JOIN Questionnaire ON Questionnaire.PatientSerNum = Patient.PatientSerNum
                    AND Questionnaire.CompletedFlag = 1
                    AND Patient.PatientId = :uid2
            ORDER BY Questionnaire.CompletionDate DESC
            LIMIT 1) Q
    LIMIT 1";

$queryOpal = $dbOpal->prepare($sqlOpal);

#opal database query run under 'and' mode to get the questionnaire that patient had answered
$sqlOpal2 = "
    SELECT DISTINCT QC.QuestionnaireName_EN AS QuestionnaireName
            FROM
                Patient
                INNER JOIN Questionnaire ON Questionnaire.PatientSerNum = Patient.PatientSerNum
                AND Questionnaire.CompletedFlag = 1
                Inner JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Questionnaire.QuestionnaireControlSerNum
                AND Patient.PatientId = :uid";

$queryOpal2 = $dbOpal->prepare($sqlOpal2);


$specialityFilter = "ClinicResources.Speciality = '$clinic'";

$activeStatusConditions = array_filter($statusConditions);
//if($specificApp === NULL) print("t");
//else print("x");

//Set the filter for SMS/OPAL status, based on the parameters.
$opalFilter = "";
if ($OPAL==NULL && $SMS == NULL) $opalFilter = "AND (Patient.SMSAlertNum IS NULL OR Patient.OpalPatient = 0)";
else if($OPAL==NULL) $opalFilter .= "AND Patient.SMSAlertNum IS NOT NULL";
else if($SMS==NULL) $opalFilter .= "AND Patient.OpalPatient = 1";
else  {
    $opal = $_GET["opal"];
    $sms = $_GET["SMS"];
    if ($opal == 1 && $sms == 1) $opalFilter = "AND (Patient.SMSAlertNum IS NOT NULL OR Patient.OpalPatient = 1)";
}


$statusFilter = " AND MV.Status IN (" . implode(",", $activeStatusConditions) . ")";
$appFilter = ($appType === "all") ? "" : " AND MV.ResourceDescription IN (" . implode(",", explode(",",$specificApp)) . ")";
$cappFilter = ($appcType === "all") ? "" : " AND MV.AppointmentCode IN (" . implode(",", explode(",",$cspecificApp)) . ")";

//$appFilter = ($appType === "all") ? "" : " AND MV.ResourceDescription IN :resDesc ";
//$cappFilter = ($appcType === "all") ? "" : " AND MV.AppointmentCode IN :appCode ";

/*print_r(explode(",",$dspecificApp));
if(in_array("Sarcoma", explode(",",$dspecificApp))) print "good";

else print "bad";*/

#ORMS database query run under 'and' mode for basic appoint information
$sql = "
    SELECT
        MV.AppointmentSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        Patient.PatientSerNum,
        Patient.SSN,
        MV.ResourceDescription,
        MV.Resource,
        MV.AppointmentCode,
        MV.Status,
        MV.ScheduledDate,
        MV.ScheduledTime,
        MV.CreationDate,
        MV.ReferringPhysician,
        (select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as CurrentCheckInTime,
        (select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as PreviousCheckInTime,
        MV.MedivisitStatus,
        (SELECT DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE TEMP_PatientQuestionnaireReview.PatientSer = Patient.PatientSerNum) AS LastQuestionnaireReview,
        Patient.OpalPatient,
        Patient.SMSAlertNum,
        Patient.ClinicalActionStatus
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = Patient.PatientSerNum
        AND MV.Status != 'Deleted'
        AND MV.ResourceDescription IN (
            SELECT DISTINCT
                ClinicResources.ResourceName
            FROM ClinicResources
            WHERE $specialityFilter
        )
        AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
        $statusFilter
        $appFilter
        $cappFilter
        $opalFilter

    ORDER BY
        MV.ScheduledDate,
        MV.ScheduledTime";


$query = $dbh->prepare($sql);

# if we are in 'or' mode or appointment filter is disable
if($andbutton == "Or"||(!$qfilter &&$afilter)) {
    $qappFilter = ($qType === "all") ? "" : " AND QC.QuestionnaireName_EN IN ('" . implode("','", explode(",", $qspecificApp)) . "')";
    $dappFilter = ($appdType === "all") ? "" : " AND DT.Name_EN IN ('" . implode("','", explode(",", $dspecificApp)) . "')";


    $sqlOpal3 = "SELECT P.PatientId,
            (select DT.Name_EN from Diagnosis D
             INNER JOIN DiagnosisCode DC ON DC.DiagnosisCode = D.DiagnosisCode
             INNER JOIN DiagnosisTranslation DT ON DC.DiagnosisTranslationSerNum = DT.DiagnosisTranslationSerNum
             $dappFilter
             where D.PatientSerNum = P.PatientSerNum
             ORDER BY D.LastUpdated DESC
             LIMIT 1) AS Name_EN,
             (SELECT Q.CompletionDate
                FROM Questionnaire Q
				Inner JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Q.QuestionnaireControlSerNum
				AND Q.CompletedFlag = 1
				$qappFilter
				where Q.PatientSerNum = P.PatientSerNum
				ORDER BY Q.CompletionDate DESC
				LIMIT 1) AS QuestionnaireCompletionDate,
			 (SELECT QC.QuestionnaireName_EN
                FROM Questionnaire Q
				Inner JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Q.QuestionnaireControlSerNum
				AND Q.CompletedFlag = 1
				$qappFilter
				where Q.PatientSerNum = P.PatientSerNum
				ORDER BY Q.CompletionDate DESC
				LIMIT 1) AS QuestionnaireName,
			(SELECT CASE
                    WHEN Q.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW() THEN 1
                        ELSE 0
                    END AS CompletedWithinLastWeek
                FROM Questionnaire Q
				Inner JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Q.QuestionnaireControlSerNum
				AND Q.CompletedFlag = 1
				$qappFilter
				where Q.PatientSerNum = P.PatientSerNum
				ORDER BY Q.CompletionDate DESC
				LIMIT 1) AS CompletedWithinLastWeek,
			(SELECT CASE
                    WHEN Q.LastUpdated BETWEEN :qDate AND NOW() THEN 1
                        ELSE 0
                    END AS RecentAnswered
                FROM Questionnaire Q
				Inner JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Q.QuestionnaireControlSerNum
				AND Q.CompletedFlag = 1
				$qappFilter
				where Q.PatientSerNum = P.PatientSerNum
				ORDER BY Q.CompletionDate DESC
				LIMIT 1) AS RecentAnswered
            FROM Patient P";

    $queryOpal3 = $dbOpal->prepare($sqlOpal3);

    $sql2 = "SELECT  Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        Patient.PatientSerNum,
        Patient.SSN,
        (SELECT DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE TEMP_PatientQuestionnaireReview.PatientSer = Patient.PatientSerNum) AS LastQuestionnaireReview,
        Patient.OpalPatient,
        Patient.SMSAlertNum,
        Patient.ClinicalActionStatus
        FROM Patient
        WHERE Patient.PatientId = :uid";


    $query2 = $dbh->prepare($sql2);
}
//if ($appFilter !== "") $query->bindValue(":resDesc", $specificApp);
//if ($cappFilter !== "") $query->bindValue(":appCode", $cspecificApp);

$listOfAppointments = [];
$mrnList = [];

# if the appointment filter is active
if(!$afilter) {

    $query->bindValue(":sDate", $sDate);
    $query->bindValue(":eDate", $eDate);

    $query->execute();
    foreach($query->fetchAll() as $row) {
        #filter apppointments on whether the patient checked in for it
        $checkInTime = $row["CurrentCheckInTime"] ?? $row["PreviousCheckInTime"] ?? NULL;

        if ($checkForArrived === TRUE && $checkForNotArrived === TRUE) $filterAppointment = FALSE;
        elseif ($checkForArrived === TRUE && $checkInTime === NULL) $filterAppointment = TRUE;
        elseif ($checkForNotArrived === TRUE && $checkInTime !== NULL) $filterAppointment = TRUE;
        else $filterAppointment = FALSE;

        if ($filterAppointment) continue;

        #if ramq expiration checking is enabled, check if the ramq is expired
        /*
            $ramqExpired = FALSE;
            if ($checkRamqExpiration) {
                $ramqInfo = AdtWebservice::getRamqInformation($row["SSN"]);
                $row["ssnExp"] = $ramqInfo["Expiration"];
                if ($ramqInfo["Status"] === "Expired" || $ramqInfo["Status"] === "Error") $ramqExpired = TRUE;
            }
        */

        #perform some processing
        if (isset($row["ssnExp"]) && $row["ssnExp"] !== NULL) $row["ssnExp"] = (new DateTime($row["ssnExp"]))->format("ym");
        $row["ScheduledTime"] = substr($row["ScheduledTime"], 0, -3);

        $queryOpal->execute(array(":uid" => $row["PatientId"], ":qDate" => $qDate, ":uid2" => $row["PatientId"]));
        $resultOpal = $queryOpal->fetchAll()[0] ?? [];

        $row["Diagnosis"] = !empty($resultOpal["Name_EN"]) ? $resultOpal["Name_EN"] : "";
        $row["QuestionnaireName"] = !empty($resultOpal["QuestionnaireName"]) ? $resultOpal["QuestionnaireName"] : "";
        if ($row["SMSAlertNum"]) $row["SMSAlertNum"] = substr($row["SMSAlertNum"], 0, 3) . "-" . substr($row["SMSAlertNum"], 3, 3) . "-" . substr($row["SMSAlertNum"], 6, 4);

        $lastCompleted = $resultOpal["QuestionnaireCompletionDate"] ?? NULL;
        $completedWithinWeek = $resultOpal["CompletedWithinLastWeek"] ?? NULL;
        $recentlyAnswered = $resultOpal["RecentAnswered"] ?? NULL;
        $row["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : "";

        if (
            ($lastCompleted !== NULL && $row["LastQuestionnaireReview"] == NULL)
            ||
            (
                ($lastCompleted !== NULL && $row["LastQuestionnaireReview"] !== NULL)
                && (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($row["LastQuestionnaireReview"]))->getTimestamp()
            )
        ) $row["QStatus"] = "red-circle";

        $answeredQuestionnaire = False;
        $queryOpal2->execute(array(":uid" => $row["PatientId"]));
        foreach($queryOpal2->fetchAll() as $questionnaireName) {
            if (in_array($questionnaireName["QuestionnaireName"], explode(",", $qspecificApp))) {
                $answeredQuestionnaire = true;
                break;
            }
        }

        if (!in_array($row["PatientId"], $mrnList)) {
            array_push($mrnList, $row["PatientId"]);
        }

        if (($appdType === "all" || in_array($row["Diagnosis"], explode(",", $dspecificApp))) && ($recentlyAnswered == 1 || $offbutton == "OFF"||$qfilter||$andbutton=='Or') && ($qType === "all" || $answeredQuestionnaire)) {
            $listOfAppointments[] = [
                "fname" => $row["FirstName"],
                "lname" => $row["LastName"],
                "mrn" => $row["PatientId"],
                "patientId" => $row["PatientSerNum"],
                "ssn" => [
                    "num" => $row["SSN"],
                    "expDate" => $row["ssnExp"] ?? NULL,
                ],
                "appName" => $row["ResourceDescription"],
                "appClinic" => $row["Resource"],
                "appType" => $row["AppointmentCode"],
                "appStatus" => $row["Status"],
                "appDay" => $row["ScheduledDate"],
                "appTime" => $row["ScheduledTime"],
                "checkin" => $checkInTime,
                "createdToday" => new DateTime($row["CreationDate"]) == new DateTime("midnight"),
                "referringPhysician" => $row["ReferringPhysician"],
                "mediStatus" => $row["MedivisitStatus"],
                "diagnosis" => $row["Diagnosis"],
                "QStatus" => $row["QStatus"],
                "opalpatient" => $row["OpalPatient"],
                "SMSAlertNum" => $row["SMSAlertNum"],
                "LastReview" => $row["LastQuestionnaireReview"],
                "CAStatus" => $row["ClinicalActionStatus"],
            ];
        }
    }
}

#if appointment filter is disable or clinical viewer is under 'or' mode
if(($andbutton=="Or"||(!$qfilter &&$afilter)) && isset($queryOpal3)&& isset($query2) ) {
    $queryOpal3->bindValue(":qDate", $qDate);
    $queryOpal3->execute();

    foreach($queryOpal3->fetchAll() as $row2) {
        $query2->execute(array(":uid" => $row2["PatientId"]));
        $resultORMS = $query2->fetchAll()[0];

        if ($resultORMS["SMSAlertNum"]) $resultORMS["SMSAlertNum"] = substr($resultORMS["SMSAlertNum"], 0, 3) .
            "-" . substr($resultORMS["SMSAlertNum"], 3, 3) . "-" . substr($resultORMS["SMSAlertNum"], 6, 4);

        $lastCompleted = $row2["QuestionnaireCompletionDate"] ?? NULL;
        $completedWithinWeek = $row2["CompletedWithinLastWeek"] ?? NULL;
        $recentlyAnswered = $row2["RecentAnswered"] ?? NULL;
        $row2["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : "";

        if (
            ($lastCompleted !== NULL && $resultORMS["LastQuestionnaireReview"] === NULL)
            ||
            (
                ($lastCompleted !== NULL && $resultORMS["LastQuestionnaireReview"] !== NULL)
                && (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($resultORMS["LastQuestionnaireReview"]))->getTimestamp()
            )
        ) $row2["QStatus"] = "red-circle";

        if (($appdType === "all" || $row2["Name_EN"]) && ($recentlyAnswered == 1 || $offbutton == "OFF") && ($qType === "all" || $row2["QuestionnaireName"]) && (!in_array($row2["PatientId"], $mrnList)) && ($resultORMS["FirstName"])
        && ($row2["QuestionnaireCompletionDate"])) {

            $listOfAppointments[] = [
                "fname" => $resultORMS["FirstName"],
                "lname" => $resultORMS["LastName"],
                "mrn" => $row2["PatientId"],
                "patientId" => $resultORMS["PatientSerNum"],
                "ssn" => [
                    "num" => $row2["SSN"],
                    "expDate" => $row2["ssnExp"] ?? NULL,
                ],
                "appName" => $row2["ResourceDescription"],
                "appClinic" => $row2["Resource"],
                "appType" => $row2["AppointmentCode"],
                "appStatus" => $row2["Status"],
                "appDay" => $row2["ScheduledDate"],
                "appTime" => $row2["ScheduledTime"],
                "checkin" => NULL,
                "createdToday" => NULL,
                "referringPhysician" => $row2["ReferringPhysician"],
                "mediStatus" => $row2["MedivisitStatus"],
                "diagnosis" => $row2["Name_EN"],
                "QStatus" => $row2["QStatus"],
                "opalpatient" => $resultORMS["OpalPatient"],
                "SMSAlertNum" => $resultORMS["SMSAlertNum"],
                "LastReview" => $resultORMS["LastQuestionnaireReview"],
                "CAStatus" => $resultORMS["ClinicalActionStatus"],
            ];
        }
    }
}

$listOfAppointments = utf8_encode_recursive($listOfAppointments);
echo json_encode($listOfAppointments);


exit;

?>
