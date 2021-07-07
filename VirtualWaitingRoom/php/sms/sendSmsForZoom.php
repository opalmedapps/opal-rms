<?php declare(strict_types = 1);

//====================================================================================
// php code to send an SMS message to a given patient using their cell number for a telemed appointment
//====================================================================================
require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Patient\PatientInterface;
use Orms\Http;
use Orms\Sms\SmsInterface;

$patientId  = $_GET["patientId"] ?? NULL;
$zoomLink   = $_GET["zoomLink"] ?? NULL;
$resName    = $_GET["resName"] ?? NULL;

if($patientId === NULL || $zoomLink === NULL || $resName === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Missing parameters!");
}

$patient = PatientInterface::getPatientById($patientId);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

if($patient->phoneNumber === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Patient has no phone number");
}

//create the message in the patient's prefered language
if($patient->languagePreference === "French") {
    $message = "Teleconsultation CUSM: $zoomLink. Clickez sur le lien pour connecter avec $resName";
}
else {
    $message = "MUHC teleconsultation: $zoomLink. Use this link to connect with $resName.";
}

SmsInterface::sendSms($patient->phoneNumber,$message);

Http::generateResponseJsonAndExit(200);
