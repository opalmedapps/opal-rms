<?php declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Appointment;
use Orms\Patient;
use Orms\Location;

$params = Http::getPostContents();

$appointmentId = $params["appointmentId"] ?? NULL;
$patientId     = $params["patientId"] ?? NULL;
$room          = $params["room"] ?? NULL;

if($room === NULL) {
    http_response_code(400);
    exit("Room is invalid");
}

if($appointmentId === NULL) {
    http_response_code(400);
    exit("Appointment id is invalid");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL)  {
    http_response_code(400);
    exit("Patient not found");
}

//after completing the appointment, un-check out the patient for that appointment
//also update the patient's location for their other appointments
Appointment::completeAppointment($appointmentId);

Location::removePatientLocationForAppointment($appointmentId);
Location::movePatientToLocation($patient,$room,$appointmentId);

?>
