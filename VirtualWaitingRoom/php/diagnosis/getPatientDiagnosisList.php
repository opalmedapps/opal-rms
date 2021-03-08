<?php declare(strict_types = 1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\Patient;
use Orms\Opal;

$patientId = (int) $_GET["patientId"];

$diagArr = DiagnosisInterface::getDiagnosisListForPatient($patientId);

// get additional diagnoses from Opal
$mrn = Patient::getPatientById($patientId)->patientId;
if($mrn !== NULL) {
    $diagArr = array_merge($diagArr,Opal::getPatientDiagnosis($mrn));
}

echo json_encode($diagArr);

?>
