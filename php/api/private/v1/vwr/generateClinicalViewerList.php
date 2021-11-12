<?php

declare(strict_types=1);
//---------------------------------------------------------------------------------------------------------------
// This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
//---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Config;
use Orms\DataAccess\ReportAccess;
use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\External\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$sDateInit              = $params["sDate"] ?? null;
$eDateInit              = $params["eDate"] ?? null;
$sTime                  = $params["sTime"] ?? null;
$eTime                  = $params["eTime"] ?? null;
$speciality             = $params["speciality"] ?? null;
$checkForArrived        = (bool) ($params["arrived"] ?? null);
$checkForNotArrived     = (bool) ($params["notArrived"] ?? null);
$opalAllowed            = (bool) ($params["opal"] ?? null);
$smsAllowed             = (bool) ($params["SMS"] ?? null);
$appType                = $params["type"] ?? null;
$specificApp            = $params["specificType"] ?? "";
$appcType               = $params["ctype"] ?? null;
$cspecificApp           = $params["cspecificType"] ?? "";
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
$activeStatusConditions = array_values(array_filter($statusConditions));

$clinicCodes = [];
if($appType !== "all" && $specificApp !== "") {
    $clinicCodes = explode("|||", $specificApp);
}

$appointmentCodes = [];
if($appcType !== "all" && $cspecificApp !== "") {
    $appointmentCodes = explode("|||", $cspecificApp);
}

$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";
$qDate = "$qDateInit $qTime";

$listOfAppointments = [];

//get ORMS patients if the appointment filter is disabled
if($afilter === false)
{
    //get questionnaire data for opal patients
    $lastCompletedQuestionnaires = json_decode(Config::getApplicationSettings()->environment->completedQuestionnairePath,true) ?? [];

    $appointments = ReportAccess::getListOfAppointmentsInDateRange(new DateTime($sDate),new DateTime($eDate),(int) $speciality,$activeStatusConditions,$clinicCodes,$appointmentCodes);

    foreach($appointments as $app)
    {
        //filter patients based on their opal and sms status
        if($app["opalEnabled"] === true && $opalAllowed === false) continue;
        if($app["phoneNumber"] !== null && $smsAllowed === false)  continue;

        //filter apppointments on whether the patient checked in for it
        if($checkForArrived === true && $checkForNotArrived === true)   ;
        elseif($checkForArrived === true && $app["checkInTime"] === null)      continue;
        elseif($checkForNotArrived === true && $app["checkInTime"] !== null)   continue;

        if($app["phoneNumber"] !== null) {
            $app["phoneNumber"] = mb_substr($app["phoneNumber"], 0, 3) ."-". mb_substr($app["phoneNumber"], 3, 3) ."-". mb_substr($app["phoneNumber"], 6, 4);
        }

        //define opal fields and fill them if the patient is an opal patient
        $app["questionnaireStatus"] = null;
        $recentlyAnswered = null;
        $answeredQuestionnaire = false;
        $lastQuestionnaireReview = null;

        if($app["opalEnabled"] === true)
        {
            $patient = PatientInterface::getPatientById($app["patientId"]) ?? throw new Exception("Unknown patient id $app[patientId]");

            //check the patient's last completed questionnaire
            $questionnaire = $lastCompletedQuestionnaires[$patient->id] ?? null;
            if($questionnaire !== null)
            {
                $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i", $qDate);
                $recentlyAnswered = $questionnaireDateLimit <= $questionnaire["completionDate"];

                $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
                $completedWithinWeek = ($oneWeekAgo <= $questionnaire["completionDate"]);

                $app["questionnaireStatus"] = ($completedWithinWeek === true) ? "green-circle" : null;

                $lastQuestionnaireReview = PatientInterface::getLastQuestionnaireReview($patient);

                if($lastQuestionnaireReview === null || $questionnaire["completionDate"] > $lastQuestionnaireReview) {
                    $app["questionnaireStatus"] = "red-circle";
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
            ($appdType === "all" || checkDiagnosis($app["patientId"], explode(",", $dspecificApp)) === true)
            && ($qfilter === true || $offbutton === "OFF" || $andbutton === "Or" || $recentlyAnswered === true)
            && ($qType === "all" || $answeredQuestionnaire === true)
        ) {
            $listOfAppointments[] = [
                "fname"         => $app["fname"],
                "lname"         => $app["lname"],
                "mrn"           => $app["mrn"],
                "site"          => $app["site"],
                "patientId"     => $app["patientId"],
                "appName"       => $app["appName"],
                "appClinic"     => $app["appClinic"],
                "appType"       => $app["appType"],
                "appStatus"     => $app["appStatus"],
                "appDay"        => $app["appDay"],
                "appTime"       => $app["appTime"],
                "checkInTime"   => $app["checkInTime"],
                "mediStatus"    => $app["mediStatus"],
                "QStatus"       => $app["questionnaireStatus"],
                "opalPatient"   => (int) $app["opalEnabled"],
                "age"           => date_diff($app["dateOfBirth"],new DateTime())->format("%y"),
                "sex"           => mb_substr($app["sex"], 0, 1),
                "PhoneNumber"   => $app["phoneNumber"],
                "LastReview"    => $lastQuestionnaireReview,
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

    $seenPatients = array_unique(array_column($listOfAppointments,"patientId"));

    foreach($patients as $pat)
    {
        //filter as many patients as possible before doing any processing

        $questionnaireDateLimit = DateTime::createFromFormatN("Y-m-d H:i", $qDate);
        $recentlyAnswered = $questionnaireDateLimit <= $pat["completionDate"];

        if($offbutton === "ON" && $recentlyAnswered === false) {
            continue;
        }

        $ormsInfo = PatientInterface::getPatientByMrn($pat["mrn"],$pat["site"]);

        if(
            $ormsInfo === null
            || in_array($ormsInfo->id,$seenPatients) === true
            || ($appdType !== "all" && checkDiagnosis($ormsInfo->id, explode(",", $dspecificApp)) === false)
            || ($ormsInfo->phoneNumber !== null && $smsAllowed === false)
        ) continue;

        $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
        $completedWithinWeek = ($oneWeekAgo <= $pat["completionDate"]);

        $pat["questionnaireStatus"] = ($completedWithinWeek === true) ? "green-circle" : null;

        $lastQuestionnaireReview = PatientInterface::getLastQuestionnaireReview($ormsInfo);

        if($lastQuestionnaireReview === null || $pat["completionDate"] > $lastQuestionnaireReview) {
            $pat["questionnaireStatus"] = "red-circle";
        }

        $phoneNumber = null;
        if($ormsInfo->phoneNumber !== null) {
            $phoneNumber = mb_substr($ormsInfo->phoneNumber, 0, 3) ."-". mb_substr($ormsInfo->phoneNumber, 3, 3) ."-". mb_substr($ormsInfo->phoneNumber, 6, 4);
        }

        $listOfAppointments[] = [
            "fname"         => $ormsInfo->firstName,
            "lname"         => $ormsInfo->lastName,
            "mrn"           => $pat["mrn"],
            "site"          => $pat["site"],
            "patientId"     => $ormsInfo->id,
            "appName"       => null,
            "appClinic"     => null,
            "appType"       => null,
            "appStatus"     => null,
            "appDay"        => null,
            "appTime"       => null,
            "checkInTime"   => null,
            "mediStatus"    => null,
            "QStatus"       => $pat["questionnaireStatus"],
            "opalPatient"   => $ormsInfo->opalStatus,
            "age"           => date_diff($ormsInfo->dateOfBirth,new DateTime())->format("%y"),
            "sex"           => mb_substr($ormsInfo->sex, 0, 1),
            "PhoneNumber"   => $phoneNumber,
            "LastReview"    => $lastQuestionnaireReview,
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
