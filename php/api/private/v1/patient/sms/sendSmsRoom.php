<?php

declare(strict_types=1);

// send an SMS message to a given patient using their cell number registered in the ORMS database

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\External\LegacyOpalAdmin\Export;
use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$params = Http::getRequestContents();

$patientId      = (int) ($params["patientId"] ?? null);
$sourceId       = $params["sourceId"];
$sourceSystem   = $params["sourceSystem"];
$roomFr         = $params["roomFr"] ?? null;
$roomEn         = $params["roomEn"] ?? null;

//temp fix for empty room strings
//some function in the virtualWaitingRoom.js calls $scope.callPatient with sendSMS = true but the destination object has no VenueEN or VenueFR defined
if($roomEn === null || $roomFr === null) {
    Http::generateResponseJsonAndExit(400, error: "Room parameter missing");
}

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

Http::generateResponseJsonAndContinue(200);

//send a notification to Opal if the patient is an Opal patient
Export::exportRoomNotification($patient, $sourceId, $sourceSystem, $roomEn, $roomFr);

if($patient->phoneNumber !== null) {
    if($patient->languagePreference === "English") {
        $message = "MUHC - Cedars Cancer Centre: Please go to $roomEn for your appointment. You will be seen shortly.";
    }
    else {
        $preposition = preg_match("/^(Salle|Porte|Réception)/", $roomEn) ? $preposition = "à la" : "au"; // If "Salle" then use "à la Salle"
        $message = "CUSM - Centre du cancer des cèdres: veuillez vous diriger $preposition $roomFr pour votre rendez-vous. Votre équipe vous verra sous peu.";
    }

    SmsInterface::sendSms($patient->phoneNumber, $message);
}
