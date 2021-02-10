<?php declare(strict_types=1);
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use GetOpt\GetOpt;

use Orms\Database;
use Orms\Sms;

// Extract the command line parameters
$opts = new GetOpt([
    ["PatientId"],
    ["message_FR"],
    ["message_EN"]
],[GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$PatientId  = $opts->getOption("PatientId") ?? "";
$message_FR = $opts->getOption("message_FR") ?? "";
$message_EN = $opts->getOption("message_EN") ?? "";

//====================================================================================
// Database
//====================================================================================
// Create MySQL DB connection
$dbh = Database::getOrmsConnection();

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
$result = $query->fetchAll()[0] ?? [];

$SMSAlertNum = $result["SMSAlertNum"] ?? "";
$LanguagePreference = $result["LanguagePreference"] ?? NULL;

if(empty($SMSAlertNum)) {
    exit("No SMS alert phone number so will not attempt to send");
}

//====================================================================================
// Message Creation
//====================================================================================
$message = ($LanguagePreference === "English") ? $message_EN : $message_FR;

//====================================================================================
// Sending
//====================================================================================
Sms::sendSms($SMSAlertNum,$message);

echo "Message should have been sent...";

?>
