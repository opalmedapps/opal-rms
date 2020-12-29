<?php
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\SmsInterface;

// Extract the command line parameters
$opts = getopt("",["PatientId:","message_FR:","message_EN:"]);

$PatientId  = $opts["PatientId"] ?? '';
$message_FR = $opts["message_FR"] ?? '';
$message_EN = $opts["message_EN"] ?? '';

//====================================================================================
// Database
//====================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

//====================================================================================
// SMS Number
//====================================================================================
# Get patient's phone number from their ID if it exists
$query = $dbh->prepare("
    SELECT
        SMSAlertNum,
        LanguagePreference
    FROM
        Patient
    WHERE
        PatientId = :mrn
");
$query->execute([":mrn" => $PatientId]);

/* Process results */
$result = $query->fetchAll()[0] ?? NULL;

$SMSAlertNum = $result["SMSAlertNum"] ?? NULL;
$LanguagePreference = $result["LanguagePreference"] ?? NULL;

if(empty($SMSAlertNum)){
    exit("No SMS alert phone number so will not attempt to send");
}

//====================================================================================
// Message Creation
//====================================================================================
$message = ($LanguagePreference === "English") ? $message_EN : $message_FR;

//====================================================================================
// Sending
//====================================================================================
SmsInterface::sendSms($SMSAlertNum,$message);

echo "Message should have been sent...";

?>
