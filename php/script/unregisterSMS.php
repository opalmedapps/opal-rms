<?php
//====================================================================================
// php code to remove patients' cell phone numbers and languagee preferences
// into ORMS
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

#print header
header('Content-Type:text/html; charset=UTF-8');

// Extract the webpage parameters
$SMSAlertNum		= $_GET["SMSAlertNum"] ?? '';

if(empty($SMSAlertNum))
{
  exit("All input parameters are required - please use back button and fill out form completely");
}

//====================================================================================
// Database - ORMS
//====================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

//====================================================================================
// Remove the input phone number from to the ORMS db
//====================================================================================

#check if any patients with the phone number exist
$querySMS = $dbh->prepare("
    SELECT
        CONCAT(Patient.LastName ,', ',Patient.FirstName) AS Name,
        Patient.PatientId AS MRN
    FROM
        Patient
    WHERE
        Patient.SMSAlertNum = ?
");
$querySMS->execute([$SMSAlertNum]);

$patients = $querySMS->fetchAll();

if($patients === []) {
    echo "No patient with number $SMSAlertNum could be found.";
    exit;
}

foreach($patients as $pat) {
    echo "Removing number from $pat[Name] ($pat[MRN])<br>";
}

$updateSMS = $dbh->prepare("
    UPDATE Patient
    SET
        Patient.SMSAlertNum = NULL,
        Patient.SMSSignupDate = NULL,
        Patient.SMSLastUpdated = CURRENT_TIMESTAMP(),
        Patient.LanguagePreference = NULL
    WHERE Patient.SMSAlertNum = ?
");
$updateSMS->execute([$SMSAlertNum]);

echo "Number removed.";


?>
