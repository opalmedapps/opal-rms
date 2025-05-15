<?php

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
if(!empty($patient->mrns)) {
    $mrn = $patient->mrns[0]->mrn ?? '';
    $site = $patient->mrns[0]->site ?? '';
    PDFExportService::triggerQuestionnaireReportGeneration(mrn: $mrn, site: $site);
}

Http::generateResponseJsonAndExit(200);
