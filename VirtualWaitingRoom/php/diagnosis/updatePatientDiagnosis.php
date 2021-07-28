<?php declare(strict_types = 1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Http;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\DateTime;
use Orms\Patient\PatientInterface;
use Orms\Hospital\OIE\Export;

$patientId           = (int) $_GET["patientId"];
$patientDiagnosisId  = (int) $_GET["patientDiagnosisId"];
$diagnosisId         = (int) $_GET["diagnosisId"];
$diagnosisDate       = new DateTime($_GET["diagnosisDate"] ?? "");
$status              = $_GET["status"];
$user                = $_GET["user"];

$updatedDiag = DiagnosisInterface::updatePatientDiagnosis($patientDiagnosisId,$diagnosisId,$diagnosisDate,$status,$user);

Http::generateResponseJsonAndContinue(200);

//export the diagnosis to external systems
$patient = PatientInterface::getPatientById($patientId);

if($patient !== NULL)
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
