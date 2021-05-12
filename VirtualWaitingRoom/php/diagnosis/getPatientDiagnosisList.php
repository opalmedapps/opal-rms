<?php declare(strict_types = 1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\Patient;
use Orms\Hospital\OIE\Fetch;

$patientId = (int) $_GET["patientId"];

$diagArr = DiagnosisInterface::getDiagnosisListForPatient($patientId);

// get additional diagnoses from Opal
$patient = Patient::getPatientById($patientId);
if($patient !== NULL) {
    $diagArr = array_merge($diagArr,Fetch::getPatientDiagnosis($patient->mrns[0]));
}

echo json_encode($diagArr);

?>
