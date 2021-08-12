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

$studies = Fetch::getStudiesForPatient($patient);

Http::generateResponseJsonAndExit(200, data: $studies);
