<?php
//==================================================================================== 
// php code to remove patients' cell phone numbers and languagee preferences
// into ORMS 
//==================================================================================== 
include_once("ScriptLoader.php");

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

// Check connection
if (!$dbh) {
    die("<br>Connection failed");
}

//==================================================================================== 
// Remove the input phone number from to the ORMS db 
//==================================================================================== 
$sqlSMS = "
	UPDATE Patient 
	SET 	Patient.SMSAlertNum = NULL,
		Patient.SMSSignupDate = NULL,
		Patient.SMSLastUpdated = CURRENT_TIMESTAMP(),
		Patient.LanguagePreference = NULL
	WHERE Patient.SMSAlertNum = '$SMSAlertNum'
  ";

echo "SMS: $sqlSMS<br>";

if ($dbh->query($sqlSMS)) {
    echo "Record updated successfully<br>";
} else {
    echo "Error updating record: " .print_r($dbh->errorInfo());
    exit("This record could not be updated. Please mark the patient's form and call Victor Matassa at 514 715 7890.<br>");
}

echo "<br>";

?>


