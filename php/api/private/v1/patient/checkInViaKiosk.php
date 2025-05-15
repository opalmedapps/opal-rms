<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\Appointment\LocationInterface;
use Orms\DateTime;
use Orms\External\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$patientId = $params["patientId"] ?? null;
$room      = $params["room"] ?? null;

if($patientId === null || $room === null) {
    Http::generateResponseJsonAndExit(400, error: "Missing inputs");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

//get the patient's appointments for the day
$appointments = AppointmentInterface::getOpenAppointments((new DateTime())->modify("midnight"),(new DateTime())->modify("tomorrow")->modify("-1 ms"),$patient);

$photoOk = null;
$nextAppointment = $appointments[0] ?? null;

if($nextAppointment !== null) {
    LocationInterface::movePatientToLocation($patient,$room, null, "KIOSK");

    if($nextAppointment["sourceSystem"] === "Aria") {
        $photoOk = Fetch::checkAriaPhotoForPatient($patient);
    }

    $nextAppointment = [
        "name"          => $nextAppointment["clinicDescription"],
        "code"          => $nextAppointment["clinicCode"],
        "datetime"      => $nextAppointment["scheduledDatetime"],
        "sourceSystem"  => $nextAppointment["sourceSystem"],
    ];
}

$output = [
    "ariaPhotoOk"     => $photoOk,
    "nextAppointment" => $nextAppointment
];

$output = Encoding::utf8_encode_recursive($output);

Http::generateResponseJsonAndExit(200, data: $output);
