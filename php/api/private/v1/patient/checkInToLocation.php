<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient;
use Orms\Location;

$params = Http::getPostContents();

$appointmentId    = $params["appointmentId"] ?? NULL;
$patientId        = $params["patientId"] ?? NULL;
$room             = $params["room"] ?? NULL;

if($room === NULL) {
    http_response_code(400);
    exit("Room is invalid");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL)  {
    http_response_code(400);
    exit("Patient not found");
}

Location::movePatientToLocation($patient,$room,$appointmentId);

http_response_code(200);

?>
