<?php
//==================================================================================== 
// registerSMS.php - php code to remove patients' cell phone numbers and languagee preferences
// into ORMS 
//==================================================================================== 
include_once("config_screens.php");

#print header
header('Content-Type:text/html; charset=UTF-8');

// Extract the webpage parameters
$SMSAlertNum		= $_GET["SMSAlertNum"];

if(empty($SMSAlertNum))
{
  exit("All input parameters are required - please use back button and fill out form completely");
}

//==================================================================================== 
// Database - ORMS
//==================================================================================== 
// Create MySQL DB connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, WAITROOM_DB);

// Check connection
if ($conn->connect_error) {
    die("<br>Connection failed: " . $conn->connect_error);
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

if ($conn->query($sqlSMS) === TRUE) {
    echo "Record updated successfully<br>";
} else {
    echo "Error updating record: " . $conn->error;
    exit("This record could not be updated. Please mark the patient's form and call Victor Matassa at 514 715 7890.<br>");
}

echo "<br>";

?>


