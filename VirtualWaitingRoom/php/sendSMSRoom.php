<?php
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================
require("loadConfigs.php");

$SMS_licencekey = SMS_licencekey;
$SMS_gatewayURL = SMS_gatewayURL;
$MUHC_SMS_webservice = MUHC_SMS_webservice;
$SMS_response = "";
$paidService = 1; # Use paid service for SMS messages

// Extract the webpage parameters
$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"];
$room_FR = $_GET["room_FR"];
$room_EN = $_GET["room_EN"];

// If "salle" then a la Salle

$room_MF = substr($room_FR,0,5);

echo "room $room_MF<br>";
$to_FR = "au";
if($room_MF == "Salle")
{
	$to_FR = "à la";
}

//====================================================================================
// Database
//====================================================================================
// Create MySQL DB connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//====================================================================================
// SMS Number
//====================================================================================
# Get patient's phone number from their ID
$sqlPhone = "
	SELECT
		Patient.SMSAlertNum,
		Patient.LanguagePreference
	FROM
		Patient
	WHERE
		Patient.PatientId = '$patientIdRVH'
		AND Patient.PatientId_MGH = '$patientIdMGH'";

/* Process results */
$queryPhone = $dbWRM->query($sqlPhone);

$SMSAlertNum;
$LanguagePreference = "";
$message = "";

$row = $queryPhone->fetch(PDO::FETCH_ASSOC);

$SMSAlertNum = $row["SMSAlertNum"];
$LanguagePreference = $row["LanguagePreference"];

$dbWRM = null;

if(empty($SMSAlertNum))
{
	echo "No SMS alert phone number so will not attempt to send";
	exit;
}

//====================================================================================
// Message Creation
//====================================================================================
if($LanguagePreference == "English")
{
	$message = "MUHC - Cedars Cancer Centre: Please go to $room_EN for your appointment. You will be seen shortly. ";
}
else
{
	$message = "CUSM - Centre du cancer des cèdres: veuillez vous diriger $to_FR $room_FR pour votre rendez-vous. Vous serez vu par votre équipe sous peu.";
}

//====================================================================================
// Sending
//====================================================================================
if($paidService == 1)
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
