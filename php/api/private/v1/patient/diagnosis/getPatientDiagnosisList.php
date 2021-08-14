<?php

declare(strict_types=1);

require __DIR__ ."/../../../../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId = (int) $params["patientId"];

$diagArr = DiagnosisInterface::getDiagnosisListForPatient($patientId);

// get additional diagnoses from Opal
$patient = PatientInterface::getPatientById($patientId);
if($patient !== null) {
    $diagArr = array_merge($diagArr, Fetch::getPatientDiagnosis($patient));
}

Http::generateResponseJsonAndExit(200,data: $diagArr);
