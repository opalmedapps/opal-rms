<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Hospital\OIE\Export;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId     = (int) ($params["patientId"] ?? null);
$height        = $params["height"] ?? null;
$weight        = $params["weight"] ?? null;
$bsa           = $params["bsa"] ?? null;
$sourceId      = $params["sourceId"] ?? null;
$sourceSystem  = $params["sourceSystem"] ?? null;

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    http_response_code(400);
    exit("Unknown patient");
}

if($height === null
    || $weight === null
    || $bsa === null
    || $sourceId === null
    || $sourceSystem === null
) {
    http_response_code(400);
    exit("Incomplete measurements");
}

PatientInterface::insertPatientMeasurement($patient, (float) $height, (float) $weight, (float) $bsa, $sourceId, $sourceSystem);

Http::generateResponseJsonAndContinue(200);

//send the update to external systems
Export::exportMeasurementPdf($patient);
