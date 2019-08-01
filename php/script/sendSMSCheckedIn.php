<?php
//==================================================================================== 
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database 
//==================================================================================== 
include_once("ScriptLoader.php");

# SMS Stuff
$smsSettings = Config::getConfigs("sms");

$SMS_licencekey = $smsSettings["SMS_LICENCE_KEY"];
$SMS_gatewayURL = $smsSettings["SMS_GATEWAY_URL"];
$MUHC_SMS_webservice = $smsSettings["SMS_WEBSERVICE_MUHC"];
$SMS_response = "";
$paidService = 1; # Use paid service for SMS messages

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
if($paidService==1)
{
  $SMS = "$SMS_gatewayURL?PhoneNumber=$SMSAlertNum&Message=$message&LicenseKey=$SMS_licencekey";

  $SMS = str_replace(' ', '%20', $SMS);
  $SMS_response = file_get_contents($SMS); 

}
else
{
  $client = new SoapClient($MUHC_SMS_webservice);

  $requestParams = [
    'mobile' => $SMSAlertNum,
    'body' => $message
  ];

  print_r($requestParams);

  $SMS_response = $client->SendSMS($requestParams);
}

echo "Message should have been sent...";

?>


