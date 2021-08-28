<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

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

Http::generateResponseJsonAndExit(200);
