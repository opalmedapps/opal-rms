<?php declare(strict_types=1);
/*
    php code to send an SMS message to a given patient using their cell number
    registered in the ORMS database
*/

//this script should be a http api but due to limitations in the kiosk auto-authentication, the kiosk can only call the backend through the command line

require __DIR__."/../../../../../vendor/autoload.php";

use GetOpt\GetOpt;

use Orms\Patient\Patient;
use Orms\Sms;

// Extract the command line parameters
$opts = new GetOpt([
    ["patientId"],
    ["messageFR"],
    ["messageEN"]
],[GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$patientId  = $opts->getOption("patientId") ?? NULL;
$messageFR = $opts->getOption("messageFR") ?? NULL;
$messageEN = $opts->getOption("messageEN") ?? NULL;

//find the patient
if($patientId === NULL || $messageEN === NULL || $messageFR === NULL) {
    exit("Missing arguments!");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL) {
    exit("Unknown patient");
}

if($patient->smsNum === NULL || $patient->languagePreference === NULL) {
    exit("No SMS alert phone number so will not attempt to send");
}

//send the sms
Sms::sendSms(
    $patient->smsNum,
    ($patient->languagePreference === "English") ? $messageEN : $messageFR
);

echo "Message should have been sent...";

?>
