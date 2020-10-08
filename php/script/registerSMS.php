<?php
//====================================================================================
// php code to insert patients' cell phone numbers and languqge preferences
// into ORMS
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Sms\SmsInterface;

#print header
header('Content-Type:text/html; charset=UTF-8');

// Extract the webpage parameters
$PatientId          = $_GET["PatientId"] ?? '';
$Ramq               = $_GET["Ramq"] ?? '';
$SMSAlertNum        = $_GET["SMSAlertNum"] ?? '';
$LanguagePreference = $_GET["LanguagePreference"] ?? '';
$Speciality         = $_GET["Speciality"];

if(empty($PatientId) || empty($Ramq) || empty($SMSAlertNum) || empty($LanguagePreference))
{
    exit("All input parameters are required - please use back button and fill out form completely");
}

//====================================================================================
// 2019-03-22 YM : Force the RAMQ to upper case
//====================================================================================
$Ramq = strtoupper($Ramq);

//====================================================================================
// Database - ORMS
//====================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

//====================================================================================
// If Patient exists in ORMS, update the preferences
//====================================================================================
// first check that the patient exists in ORMS
$queryPatient = $dbh->prepare("
	SELECT Patient.LastName
	FROM Patient
	WHERE  Patient.PatientId = :mrn
");
$queryPatient->execute([":mrn" => $PatientId]);

/* Process results */
$LastName = $queryPatient->fetchAll()[0]["LastName"] ?? NULL;

if(empty($LastName))
{
    exit("This patient does not exist in either ORMS. Patient must have an appointment in order to be set up for SMS<br>");
}

echo "Patient already exists in ORMS - will update...<br>";

//====================================================================================
// Write to the ORMS db - either an update if patient already exists in ORMS
//====================================================================================
$updateSms = $dbh->prepare("
    UPDATE Patient
    SET
        Patient.SMSAlertNum = :smsNum,
        Patient.SMSSignupDate = NOW(),
        Patient.SMSLastUpdated = NOW(),
        Patient.LanguagePreference = :language
    WHERE
        Patient.PatientId = :mrn
        AND Patient.SSN = :ssn
");
$updateSms->execute([
    ":smsNum"   => $SMSAlertNum,
    ":language" => $LanguagePreference,
    ":mrn"      => $PatientId,
    "ssn"       => $Ramq
]);

echo "Record updated successfully<br>";

//====================================================================================
// Confirmation Message Creation
//====================================================================================
$messageList = SmsInterface::getPossibleSmsMessages();

$message = $messageList[$Speciality]["GENERAL"]["REGISTRATION"][$LanguagePreference]["Message"] ?? NULL;

if($message === NULL) {
    exit("Registration message not defined");
}

//====================================================================================
// Sending
//====================================================================================
SmsInterface::sendSms($SMSAlertNum,$message);

echo "Message should have been sent...";

?>
