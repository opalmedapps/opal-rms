<?php

declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Hospital\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$patientId = $_GET["patientId"] ?? null;

if($patientId === null) {
    Http::generateResponseJsonAndExit(400, error: "Empty id");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

$clinicianQuestionnaires = Fetch::getListOfCompletedQuestionnairesForPatient($patient);
$clinicianQuestionnaires = array_filter($clinicianQuestionnaires, fn($x) => $x["respondentTitle"] === "Clinician");

Http::generateResponseJsonAndExit(200, data: $clinicianQuestionnaires);
