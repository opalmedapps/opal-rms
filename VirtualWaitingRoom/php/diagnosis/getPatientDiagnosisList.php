<?php

declare(strict_types=1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Fetch;
use Orms\Patient\PatientInterface;

$patientId = (int) $_GET["patientId"];

$diagArr = DiagnosisInterface::getDiagnosisListForPatient($patientId);

// get additional diagnoses from Opal
$patient = PatientInterface::getPatientById($patientId);
if($patient !== null) {
    $diagArr = array_merge($diagArr, Fetch::getPatientDiagnosis($patient));
}

echo json_encode($diagArr);
