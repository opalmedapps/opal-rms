<?php declare(strict_types = 1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\DateTime;
use Orms\Patient\Patient;
use Orms\Hospital\OIE\Export;

$patientId      = (int) $_GET["patientId"];
$diagnosisId    = (int) $_GET["diagnosisId"];
$diagnosisDate  = new DateTime($_GET["diagnosisDate"]);
$user           = $_GET["user"];

$newDiag = DiagnosisInterface::insertPatientDiagnosis($patientId,$diagnosisId,$diagnosisDate,$user);

//export the diagnosis to external systems
$patient = Patient::getPatientById($patientId);

if($patient !== NULL) {
    Export::exportPatientDiagnosis(
        $patient,
        $newDiag->diagnosis->id, // $newDiag->id,
        $newDiag->diagnosis->subcode,
        $newDiag->createdDate,
        $newDiag->diagnosis->subcodeDescription,
        ""
    );
}
