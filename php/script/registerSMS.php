<?php
//====================================================================================
// php code to insert patients' cell phone numbers and languqge preferences
// into ORMS
//====================================================================================
include_once("ScriptLoader.php");

#print header
header('Content-Type:text/html; charset=UTF-8');

# SMS Stuff
$smsSettings = Config::getConfigs("sms");

$SMS_licencekey = $smsSettings["SMS_LICENCE_KEY"];
$SMS_gatewayURL = $smsSettings["SMS_GATEWAY_URL"];

// Extract the webpage parameters
$PatientId		= $_GET["PatientId"] ?? '';
$Ramq 			= $_GET["Ramq"] ?? '';
$SMSAlertNum		= $_GET["SMSAlertNum"] ?? '';
$LanguagePreference	= $_GET["LanguagePreference"] ?? '';

if( empty($PatientId) || empty($Ramq) || empty($SMSAlertNum) || empty($LanguagePreference) )
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

// Check connection
if (!$dbh) {
    die("<br>Connection failed");
}

//====================================================================================
// If Patient exists in ORMS, update the preferences
//====================================================================================
// first check that the patient exists in ORMS
$sqlPatient= "
	SELECT Patient.LastName
	FROM Patient
	WHERE  Patient.PatientId =  '$PatientId'
";

/* Process results */
$result = $dbh->query($sqlPatient);

$LastName;
if ($result->rowCount() > 0) {
    // output data of each row
    $row = $result->fetch();

    $LastName = $row["LastName"];
}

$PatientInORMS;
if(!empty($LastName) )
{
  $PatientInORMS = TRUE;
  echo "Patient already exists in ORMS - will update...<br>";
}
else
{
  $PatientInORMS = FALSE;
}

//====================================================================================
// Exit now if the patient does not exist in either ORM
//====================================================================================
if(!$PatientInORMS)
{
  exit("This patient does not exist in either ORMS. Patient must have an appointment in order to be set up for SMS<br>");
}

//====================================================================================
// Write to the ORMS db - either an update if patient already exists in ORMS
//====================================================================================
if($PatientInORMS)
{
  $sqlSMS = "
	UPDATE Patient
    SET
        Patient.SMSAlertNum='$SMSAlertNum',
        Patient.SMSSignupDate = NOW(),
		Patient.SMSLastUpdated = NOW(),
		Patient.LanguagePreference='$LanguagePreference'
	WHERE Patient.PatientId = '$PatientId' AND Patient.SSN = '$Ramq'
  ";
}

echo "SMS: $sqlSMS<br>";


if ($dbh->query($sqlSMS)) {
    echo "Record updated successfully<br>";
} else {
    echo "Error updating record: " . print_r($dbh->errorInfo());
    exit("This record could not be updated. Please mark the patient's form and contact opal@muhc.mcgill.ca.<br>");
}

echo "<br>";

//====================================================================================
// Confirmation Message Creation
//====================================================================================
if($LanguagePreference == "English")
{
  $message = "MUHC: You are registered for SMS notifications. To unsubscribe, please inform the reception. To check-in for an appointment, reply to this number with \"arrive\".";
}
else
{
  #$message = "CUSM - Centre du cancer des c&agrave;dres: vous &ecirc;tes enregistr&eacute;(e) pour recevoir des notifications par message texte pour vos rendez-vous. Pour vous d&eacute;sabonner, veuillez en informer la r&eacute;ception en tout temps. [On ne peut r&eacute;pondre &agrave; ce num&eacute;ro]";

  $message = "CUSM: l'inscription pour les notifications est confirmée. Pour vous désabonner, veuillez informer la réception. Pour enregistrer pour un rendez-vous, répondez à ce numéro avec \"arrive\".";
}

//====================================================================================
// Sending
//====================================================================================
$fields = [
    "Body" => $message,
    "LicenseKey" => $SMS_licencekey,
    "To" => [$SMSAlertNum],
    "Concatenate" => TRUE,
    "UseMMS" => FALSE
];

$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL             => $SMS_gatewayURL,
    CURLOPT_POST            => TRUE,
    CURLOPT_POSTFIELDS      => json_encode($fields),
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
]);
$response = curl_exec($curl);
// $headers = curl_getinfo($curl);

echo "Message should have been sent...";

?>
