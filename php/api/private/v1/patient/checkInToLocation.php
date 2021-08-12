<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\Location;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getPostContents();

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

Location::movePatientToLocation($patient, $room, $appointmentId);

Http::generateResponseJsonAndExit(200);
