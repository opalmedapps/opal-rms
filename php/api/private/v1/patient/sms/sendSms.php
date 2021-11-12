<?php

declare(strict_types=1);

require __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$params = Http::getRequestContents();

$patientId  = $params["patientId"] ?? null;
$messageEN  = $params["messageEN"] ?? null;
$messageFR  = $params["messageFR"] ?? null;

//find the patient
if($patientId === null || $messageEN === null || $messageFR === null) {
    Http::generateResponseJsonAndExit(400, error: "Missing arguments");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

Http::generateResponseJsonAndContinue(200);

//send the sms
if($patient->phoneNumber !== null && $patient->languagePreference !== null) {
    SmsInterface::sendSms(
        $patient->phoneNumber,
        ($patient->languagePreference === "English") ? $messageEN : $messageFR
    );
}
