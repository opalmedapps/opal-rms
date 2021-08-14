<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Export;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId           = (int) $params["patientId"];
$patientDiagnosisId  = (int) $params["patientDiagnosisId"];
$diagnosisId         = (int) $params["diagnosisId"];
$diagnosisDate       = new DateTime($params["diagnosisDate"] ?? "");
$status              = $params["status"];
$user                = $params["user"];

$updatedDiag = DiagnosisInterface::updatePatientDiagnosis($patientDiagnosisId, $diagnosisId, $diagnosisDate, $status, $user);

Http::generateResponseJsonAndContinue(200);

//export the diagnosis to external systems
$patient = PatientInterface::getPatientById($patientId);

if($patient !== null)
{
    Export::exportPatientDiagnosis(
        $patient,
        $updatedDiag->id,
        $updatedDiag->diagnosis->subcode,
        $updatedDiag->createdDate,
        $updatedDiag->diagnosis->subcodeDescription,
        "",
        $updatedDiag->status
    );
}
