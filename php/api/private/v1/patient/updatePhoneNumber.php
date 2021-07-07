<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$patientId          = $_GET["patientId"] ?? NULL;
$phoneNumber        = $_GET["phoneNumber"] ?? NULL;
$languagePreference = $_GET["languagePreference"] ?? NULL;
$specialityGroupId  = $_GET["specialityGroupId"] ?? NULL;

if($patientId === NULL || $languagePreference === NULL || $specialityGroupId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Input parameters are missing");
}

//find the patient and update their phone number (or remove it)
$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

$patient = PatientInterface::updatePatientInformation($patient,phoneNumber: $phoneNumber,languagePreference: $languagePreference);

//send the patient a registration success message if the patient has a phone number
$messageList = SmsInterface::getPossibleSmsMessages();

$message = $messageList[$specialityGroupId]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"] ?? NULL;

if($message === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Registration message not defined");
}

//print a message and close the connection so that the client does not wait
Http::generateResponseJsonAndContinue(200);

if($patient->phoneNumber !== NULL) {
    SmsInterface::sendSms($patient->phoneNumber,$message);
}
