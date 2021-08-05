<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Hospital\OIE\Fetch;

$patientId = $_GET["patientId"] ?? NULL;

if($patientId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Empty id");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

Http::generateResponseJsonAndExit(200,data: Fetch::getListOfCompletedQuestionnairesForPatient($patient));
