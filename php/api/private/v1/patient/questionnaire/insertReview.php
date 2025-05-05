<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\External\PDFExportService;

$params = Http::getRequestContents();

$patientId          = $params["patientId"] ?? null;
$user               = $params["user"] ?? null;

if($patientId === null || $user === null) {
    Http::generateResponseJsonAndExit(400, error: "Incomplete fields");
}

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

PatientInterface::insertQuestionnaireReview($patient,$user);

// call the endpoint that generates a questionnaire PDF report and submits it to the OIE
if (!empty($patient->mrns)) {
    $mrn = $patient->mrns[0]->mrn ?? '';
    $site = $patient->mrns[0]->site ?? '';
    PDFExportService::triggerQuestionnaireReportGeneration(mrn: $mrn, site: $site);
} else {
    error_log("Patient with ID {$patientId} has no MRNs");
}

Http::generateResponseJsonAndExit(200);
