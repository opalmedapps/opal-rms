<?php

declare(strict_types=1);

//script to insert patient measurements in the database
//also exports the measurement update to other systems

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Hospital\OIE\Export;
use Orms\Http;
use Orms\Patient\PatientInterface;

$patientId     = (int) ($_GET["patientId"] ?? null);
$height        = $_GET["height"] ?? null;
$weight        = $_GET["weight"] ?? null;
$bsa           = $_GET["bsa"] ?? null;
$sourceId      = $_GET["sourceId"] ?? null;
$sourceSystem  = $_GET["sourceSystem"] ?? null;

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
