<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$patientId          = $_GET["patientId"] ?? null;
$phoneNumber        = $_GET["phoneNumber"] ?? null;
$languagePreference = $_GET["languagePreference"] ?? null;
$specialityGroupId  = $_GET["specialityGroupId"] ?? null;

if($patientId === null || $languagePreference === null || $specialityGroupId === null) {
    Http::generateResponseJsonAndExit(400, error: "Input parameters are missing");
}

//find the patient and update their phone number (or remove it)
$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

$patient = PatientInterface::updatePatientInformation($patient, phoneNumber: $phoneNumber, languagePreference: $languagePreference);

//send the patient a registration success message if the patient has a phone number
if($patient->phoneNumber !== null)
{
    $messageList = SmsInterface::getPossibleSmsMessages();
    $message = $messageList[$specialityGroupId]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"] ?? null;

    if($message === null) {
        Http::generateResponseJsonAndExit(400, error: "Registration message not defined");
    }

    //print a message and close the connection so that the client does not wait
    Http::generateResponseJsonAndContinue(200);
    SmsInterface::sendSms($patient->phoneNumber, $message);
}

Http::generateResponseJsonAndExit(200);
