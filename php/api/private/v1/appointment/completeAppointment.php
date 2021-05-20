<?php declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Appointment;
use Orms\Patient\Patient;
use Orms\Location;

$params = Http::getPostContents();

$appointmentId = $params["appointmentId"] ?? NULL;
$patientId     = $params["patientId"] ?? NULL;
$room          = $params["room"] ?? NULL;

if($room === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Room is invalid");
}

if($appointmentId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Appointment id is invalid");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL)  {
    Http::generateResponseJsonAndExit(400,error: "Patient not found");
}

//after completing the appointment, un-check out the patient for that appointment
//also update the patient's location for their other appointments
Appointment::completeAppointment($appointmentId);

Location::removePatientLocationForAppointment($appointmentId);
Location::movePatientToLocation($patient,$room,$appointmentId);
