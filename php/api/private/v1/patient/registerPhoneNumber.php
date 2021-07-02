<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$mrn                = $_GET["mrn"] ?? NULL;
$site               = $_GET["site"] ?? NULL;
$phoneNumber        = $_GET["phoneNumber"] ?? NULL;
$languagePreference = $_GET["languagePreference"] ?? NULL;
$specialityGroupId  = $_GET["specialityGroupId"] ?? NULL;

if($mrn === NULL || $site === NULL || $phoneNumber === NULL || $languagePreference === NULL || $specialityGroupId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "All input parameters are required - please use back button and fill out form completely");
}

//phone number must be exactly 10 digits
if(!preg_match("/[0-9]{10}/",$phoneNumber)) {
    Http::generateResponseJsonAndExit(400,error: "Invalid phone number");
}

//find the patient and update their phone number
$patient = PatientInterface::getPatientByMrn($mrn,$site);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

PatientInterface::updatePatientInformation($patient,phoneNumber: $phoneNumber,languagePreference: $languagePreference);

//send the patient a registration success message
$messageList = SmsInterface::getPossibleSmsMessages();

$message = $messageList[$specialityGroupId]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"] ?? NULL;

if($message === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Registration message not defined");
}

SmsInterface::sendSms($phoneNumber,$message);

Http::generateResponseJsonAndExit(200);
