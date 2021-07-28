<?php declare(strict_types = 1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Http;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\DateTime;
use Orms\Patient\PatientInterface;
use Orms\Hospital\OIE\Export;

$patientId      = (int) $_GET["patientId"];
$diagnosisId    = (int) $_GET["diagnosisId"];
$diagnosisDate  = new DateTime($_GET["diagnosisDate"]);
$user           = $_GET["user"];

$newDiag = DiagnosisInterface::insertPatientDiagnosis($patientId,$diagnosisId,$diagnosisDate,$user);

Http::generateResponseJsonAndContinue(200);

//export the diagnosis to external systems
$patient = PatientInterface::getPatientById($patientId);

if($patient !== NULL) {
    Export::exportPatientDiagnosis(
        $patient,
        $newDiag->id,
        $newDiag->diagnosis->subcode,
        $newDiag->createdDate,
        $newDiag->diagnosis->subcodeDescription,
        "",
        $newDiag->status
    );
}
