<?php declare(strict_types=1);

// send an SMS message to a given patient using their cell number registered in the ORMS database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Patient\Patient;
use Orms\Sms;
use Orms\Hospital\OIE\Export;

// Extract the webpage parameters
$patientId = (int) ($_GET["patientId"] ?? NULL);
$sourceId = $_GET["sourceId"];
$sourceSystem = $_GET["sourceSystem"];
$roomFr = $_GET["roomFr"];
$roomEn = $_GET["roomEn"];

$patient = Patient::getPatientById($patientId);

if($patient === NULL || $patient->smsNum === NULL) {
    echo "No SMS alert phone number so will not attempt to send";
    exit;
}

if($patient->languagePreference === "English") {
    $message = "MUHC - Cedars Cancer Centre: Please go to $roomEn for your appointment. You will be seen shortly.";
}
else {
    $preposition = preg_match("/^(Salle|Porte|Réception)/",$roomEn) ? $preposition = "à la" : "au"; // If "Salle" then use "à la Salle"
    $message = "CUSM - Centre du cancer des cèdres: veuillez vous diriger $preposition $roomFr pour votre rendez-vous. Votre équipe vous verra sous peu.";
}

Sms::sendSms($patient->smsNum,$message);

//send a notification to Opal if the patient is an Opal patient
Export::exportPushNotification($patient,$sourceId,$roomEn,$roomFr);

?>
