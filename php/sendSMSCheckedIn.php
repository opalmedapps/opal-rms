<?php
//==================================================================================== 
// sendSMSRoom.php - php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database 
//==================================================================================== 
include_once("config_screens.php");

# SMS Stuff
$SMS_licencekey = SMS_licencekey;
$SMS_gatewayURL = SMS_gatewayURL;
$MUHC_SMS_webservice = MUHC_SMS_webservice;
$SMS_response = "";
$paidService = 1; # Use paid service for SMS messages

// Extract the webpage parameters
$PatientId		= $_GET["PatientId"];
$message_FR		= $_GET["message_FR"];
$message_EN		= $_GET["message_EN"];

//==================================================================================== 
// Database
//==================================================================================== 
// Create MySQL DB connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, WAITROOM_DB);

// Check connection
if ($conn->connect_error) {
    die("<br>Connection failed: " . $conn->connect_error);
} 

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
$result = $conn->query($sqlPhone);

$SMSAlertNum;
$LanguagePreference = "";
$message = "";

if ($result->num_rows > 0) {
    // output data of each row
    $row = $result->fetch_assoc(); 
  
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
   
  echo "SMS: $SMS<br>"; 

  $SMS = str_replace(' ', '%20', $SMS);
  $SMS_response = file_get_contents($SMS); 

}
else
{
  $client = new SoapClient($MUHC_SMS_webservice);

  echo "got to here<br>";

  $requestParams = array(
    'mobile' => $SMSAlertNum,
    'body' => $message
  );

  print_r($requestParams);
  echo "<br>";

  $SMS_response = $client->SendSMS($requestParams);
}

print_r($SMS_response);
echo "<br>message should have been sent...<br>";


?>


