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
$MUHC_SMS_webservice = $smsSettings["SMS_WEBSERVICE_MUHC"];
$SMS_response = "";
$paidService = 1; # Use paid service for SMS messages

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
	SET 	Patient.SMSAlertNum='$SMSAlertNum',
		Patient.SMSSignupDate = CASE WHEN Patient.SMSSignupDate = '0000-00-00 00:00:00' THEN CURRENT_TIMESTAMP() ELSE Patient.SMSSignupDate END,
		Patient.SMSLastUpdated = CURRENT_TIMESTAMP(),
		Patient.LanguagePreference='$LanguagePreference'
	WHERE Patient.PatientId = '$PatientId' AND Patient.SSN = '$Ramq'
  ";
}

echo "SMS: $sqlSMS<br>";


if ($dbh->query($sqlSMS)) {
    echo "Record updated successfully<br>";
} else {
    echo "Error updating record: " . print_r($dbh->errorInfo());
    exit("This record could not be updated. Please mark the patient's form and call Victor Matassa at 514 715 7890.<br>");
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
if($paidService==1)
{
  $SMS = "$SMS_gatewayURL?PhoneNumber=$SMSAlertNum&Message=$message&LicenseKey=$SMS_licencekey";

  echo "SMS: $SMS<br>";

  $SMS = str_replace(' ', '%20', $SMS);
  $SMS_response = file_get_contents($SMS);

}
else
{
  $client = new SoapClient($MUHC_SMS_webservice);

  echo "got to here<br>";

  $requestParams = [
    'mobile' => $SMSAlertNum,
    'body' => $message
  ];

  print_r($requestParams);
  echo "<br>";

  $SMS_response = $client->SendSMS($requestParams);
}

print_r($SMS_response);
echo "<br>message should have been sent...<br>";

?>
