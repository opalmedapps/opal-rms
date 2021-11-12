<?php

declare(strict_types=1);
/*
    php code to send an SMS message to a given patient using their cell number
    registered in the ORMS database
*/

//this script should be a http api but due to limitations in the kiosk auto-authentication, the kiosk can only call the backend through the command line

require __DIR__."/../../../../../../vendor/autoload.php";

use GetOpt\GetOpt;

use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

// Extract the command line parameters
$opts = new GetOpt([
    ["patientId"],
    ["messageFR"],
    ["messageEN"]
], [GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$patientId  = $opts->getOption("patientId") ?? null;
$messageFR = $opts->getOption("messageFR") ?? null;
$messageEN = $opts->getOption("messageEN") ?? null;

//find the patient
if($patientId === null || $messageEN === null || $messageFR === null) {
    exit("Missing arguments!");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    exit("Unknown patient");
}

if($patient->phoneNumber === null || $patient->languagePreference === null) {
    exit("No SMS alert phone number so will not attempt to send");
}

//send the sms
SmsInterface::sendSms(
    $patient->phoneNumber,
    ($patient->languagePreference === "English") ? $messageEN : $messageFR
);

echo "Message should have been sent...";
