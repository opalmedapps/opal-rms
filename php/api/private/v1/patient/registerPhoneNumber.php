<?php declare(strict_types=1);
//====================================================================================
// php code to insert patients' cell phone numbers and languqge preferences
// into ORMS
//====================================================================================
require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient;
use Orms\Sms;

$mrn                = $_GET["mrn"] ?? NULL;
$site               = $_GET["site"] ?? NULL;
$phoneNumber        = $_GET["phoneNumber"] ?? NULL;
$languagePreference = $_GET["languagePreference"] ?? NULL;
$speciality         = $_GET["speciality"] ?? NULL;

if($mrn === NULL || $site === NULL || $phoneNumber === NULL || $languagePreference === NULL || $speciality === NULL) {
    Http::generateResponseJsonAndExit(400,error: "All input parameters are required - please use back button and fill out form completely");
}

//phone number must be exactly 10 digits
if(!preg_match("/[0-9]{10}/",$phoneNumber)) {
    Http::generateResponseJsonAndExit(400,error: "Invalid phone number");
}

//find the patient and update their phone number
$patient = Patient::getPatientByMrn($mrn,$site);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

$patient = $patient->updatePhoneNumber($phoneNumber,$languagePreference);

//send the patient a registration success message
$messageList = Sms::getPossibleSmsMessages();

$message = $messageList[$speciality]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"] ?? NULL;

if($message === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Registration message not defined");
}

Sms::sendSms($phoneNumber,$message);

Http::generateResponseJsonAndExit(200);

?>
