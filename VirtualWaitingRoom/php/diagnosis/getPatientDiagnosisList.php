<?php declare(strict_types = 1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\Patient;
use Orms\Opal;

$patientId = (int) $_GET["patientId"];

$mrn = Patient::getPatientById($patientId)->patientId;

//get additional diagnoses from Opal
Opal::getPatientDiagnosis($mrn);

echo json_encode(DiagnosisInterface::getDiagnosisListForPatient($patientId));

?>
