<?php
//====================================================================================
// php code to send an SMS message to a given patient using their cell number
// registered in the ORMS database
//====================================================================================

require_once __DIR__."/../../../vendor/autoload.php";
require("../loadConfigs.php");

use Orms\Config;
use Orms\Sms\SmsInterface;

// Extract the webpage parameters
$patientId = $_GET["patientId"];
$room_FR = $_GET["room_FR"];
$room_EN = $_GET["room_EN"];

// If "salle" then a la Salle

$to_FR = "au";
if(preg_match("/^(Salle|Porte|Réception)/",$room_FR))
{
    $to_FR = "à la";
}

//====================================================================================
// Database
//====================================================================================
$dbh = Config::getDatabaseConnection("ORMS");

//====================================================================================
// SMS Number
//====================================================================================
# Get patient's phone number from their ID
$queryPhone = $dbh->prepare("
    SELECT
        Patient.SMSAlertNum,
        Patient.LanguagePreference
    FROM
        Patient
    WHERE
        Patient.PatientSerNum = :pSer
");
$queryPhone->execute([
    ":pSer" => $patientId
]);

$row = $queryPhone->fetchAll()[0];

$SMSAlertNum = $row["SMSAlertNum"] ?? NULL;
$LanguagePreference = $row["LanguagePreference"] ?? NULL;

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
    $message = "CUSM - Centre du cancer des cèdres: veuillez vous diriger $to_FR $room_FR pour votre rendez-vous. Votre équipe vous verra sous peu.";
}

//====================================================================================
// Sending
//====================================================================================
SmsInterface::sendSms($SMSAlertNum,$message);

echo "<br>message should have been sent...<br>";

?>
