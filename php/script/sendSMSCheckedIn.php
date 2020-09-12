<?php
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

# SMS Stuff
$smsSettings = Config::getConfigs("sms");

$SMS_licencekey = $smsSettings["SMS_LICENCE_KEY"];
$SMS_gatewayURL = $smsSettings["SMS_GATEWAY_URL"];

// Extract the command line parameters
$opts = getopt(null,["PatientId:","message_FR:","message_EN:"]);

$PatientId		= $opts["PatientId"] ?? '';
$message_FR		= $opts["message_FR"] ?? '';
$message_EN		= $opts["message_EN"] ?? '';

//====================================================================================
// Database
//====================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

//====================================================================================
// SMS Number
//====================================================================================
# Get patient's phone number from their ID if it exists
$sqlPhone = "
	SELECT  SMSAlertNum, LanguagePreference
	FROM  Patient
	WHERE  PatientId =  '$PatientId'
";

/* Process results */
$result = $dbh->query($sqlPhone);

$SMSAlertNum;
$LanguagePreference = "";
$message = "";

if ($result->rowCount() > 0) {
    // output data of each row
    $row = $result->fetch();

    $SMSAlertNum 	= $row["SMSAlertNum"];
    $LanguagePreference = $row["LanguagePreference"];
}

if(empty($SMSAlertNum) )
{
  exit("No SMS alert phone number so will not attempt to send");
}

//====================================================================================
// Message Creation
//====================================================================================
if($LanguagePreference == "English")
{
  $message = $message_EN;
}
else
{
  $message = $message_FR;
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
$response = curl_exec($curl);
// $headers = curl_getinfo($curl);

echo "Message should have been sent...";

?>
