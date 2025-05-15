<?php

declare(strict_types=1);

// Fetches and returns a graph compatible json object containing a patient's measurements

require_once __DIR__ ."/../../../../../../vendor/autoload.php";

use Orms\Document\Measurement\Generator;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId = (int) ($params["patientId"] ?? null);

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

$historicalChart = Generator::generateChartArray($patient);

Http::generateResponseJsonAndExit(200, data: $historicalChart);
