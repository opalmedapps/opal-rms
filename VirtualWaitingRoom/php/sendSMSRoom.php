<?php
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================
require("loadConfigs.php");

$SMS_licencekey = SMS_licencekey;
$SMS_gatewayURL = SMS_gatewayURL;

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
	$message = "MUHC - Cedars Cancer Centre: Please go to $room_EN for your appointment. You will be seen shortly.";
}
else
{
    $message = "CUSM - Centre du cancer des cèdres: veuillez vous diriger $to_FR $room_FR pour votre rendez-vous. Votre équipe vou verra sous peu.";
}

//====================================================================================
// Sending
//====================================================================================
$fields = [
    "Body" => $message,
    "LicenseKey" => $SMS_licencekey,
    "To" => [$SMSAlertNum],
    "Concatenate" => TRUE,
    "UseMMS" => FALSE,
    "IsUnicode" => TRUE
];

$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL             => $SMS_gatewayURL,
    CURLOPT_POST            => TRUE,
    CURLOPT_POSTFIELDS      => json_encode($fields),
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
]);
curl_exec($curl);

echo "<br>message should have been sent...<br>";

?>
