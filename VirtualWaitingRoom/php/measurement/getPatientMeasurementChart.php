<?php

declare(strict_types=1);

// Fetches and returns a highchart compatible json object containing a patient's measurements

require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Document\Measurement\Generator;
use Orms\Patient\PatientInterface;

$patientId = (int) ($_GET["patientId"] ?? null);

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    http_response_code(400);
    exit("Unknown patient");
}

$historicalChart = Generator::generateChartArray($patient);

//encode and return the chart as a json object
echo json_encode($historicalChart, JSON_NUMERIC_CHECK);
