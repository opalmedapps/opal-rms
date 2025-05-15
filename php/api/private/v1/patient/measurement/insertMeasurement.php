<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\External\OIE\Export;
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
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

if($height === null
    || $weight === null
    || $bsa === null
    || $sourceId === null
    || $sourceSystem === null
) {
    Http::generateResponseJsonAndExit(400, error: "Incomplete measurements");
}

PatientInterface::insertPatientMeasurement($patient, (float) $height, (float) $weight, (float) $bsa, $sourceId, $sourceSystem);

Http::generateResponseJsonAndContinue(200);

//send the update to external systems
Export::exportMeasurementPdf($patient);
