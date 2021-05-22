<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;
use Orms\Appointment\Location;

$params = Http::getPostContents();

$appointmentId    = $params["appointmentId"] ?? NULL;
$patientId        = $params["patientId"] ?? NULL;
$room             = $params["room"] ?? NULL;

if($room === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Room is invalid");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL)  {
    Http::generateResponseJsonAndExit(400,error: "Patient not found");
}

Location::movePatientToLocation($patient,$room,$appointmentId);

Http::generateResponseJsonAndExit(200);
