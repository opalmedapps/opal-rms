<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\Appointment;
use Orms\Appointment\Location;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$appointmentId = $params["appointmentId"] ?? null;
$patientId     = $params["patientId"] ?? null;
$room          = $params["room"] ?? null;

if($room === null) {
    Http::generateResponseJsonAndExit(400, error: "Room is invalid");
}

if($appointmentId === null) {
    Http::generateResponseJsonAndExit(400, error: "Appointment id is invalid");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

//after completing the appointment, un-check out the patient for that appointment
//also update the patient's location for their other appointments
Appointment::completeAppointment($appointmentId);

Location::removePatientLocationForAppointment($appointmentId);
Location::movePatientToLocation($patient, $room, $appointmentId);
