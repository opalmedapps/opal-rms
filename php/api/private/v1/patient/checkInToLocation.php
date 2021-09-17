<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\LocationInterface;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$appointmentId    = $params["appointmentId"] ?? null;
$patientId        = $params["patientId"] ?? null;
$room             = $params["room"] ?? null;

if($room === null) {
    Http::generateResponseJsonAndExit(400, error: "Room is invalid");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

Http::generateResponseJsonAndContinue(200);

LocationInterface::movePatientToLocation($patient, $room, $appointmentId);
