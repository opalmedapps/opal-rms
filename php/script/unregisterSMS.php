<?php declare(strict_types=1);
//====================================================================================
// php code to remove patients' cell phone numbers and languagee preferences
// into ORMS
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Database;

#print header
header('Content-Type:text/html; charset=UTF-8');

// Extract the webpage parameters
$SMSAlertNum = $_GET["SMSAlertNum"] ?? '';

if(empty($SMSAlertNum))
{
  exit("All input parameters are required - please use back button and fill out form completely");
}

//====================================================================================
// Database - ORMS
//====================================================================================
// Create MySQL DB connection
$dbh = Database::getOrmsConnection();

//====================================================================================
// Remove the input phone number from to the ORMS db
//====================================================================================

#check if any patients with the phone number exist
$querySMS = $dbh->prepare("
    SELECT
        CONCAT(LastName ,', ',FirstName) AS Name,
        PatientId AS MRN
    FROM
        Patient
    WHERE
        SMSAlertNum = ?
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
        SMSAlertNum = NULL,
        SMSSignupDate = NULL,
        SMSLastUpdated = CURRENT_TIMESTAMP(),
        LanguagePreference = NULL
    WHERE
        SMSAlertNum = ?
");
$updateSMS->execute([$SMSAlertNum]);

echo "Number removed.";


?>
