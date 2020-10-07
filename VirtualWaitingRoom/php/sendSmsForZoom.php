<?php declare(strict_types = 1);

#====================================================================================
# php code to send an SMS message to a given patient using their cell number
# registered in the ORMS database
#====================================================================================
require("loadConfigs.php");

use Orms\Config;
use Orms\Sms;

# Extract the webpage parameters

$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"] ?? "";
$zoomLink     = $_GET["zoomLink"];
$resName      = $_GET["resName"];

#find the patient in ORMS
$dbh = Config::getDatabaseConnection("ORMS");

$queryOrms = $dbh->prepare("
    SELECT
        Patient.SMSAlertNum,
	Patient.LanguagePreference
    FROM
        Patient
    WHERE
        Patient.PatientId = :patIdRVH
        AND Patient.PatientId_MGH = :patIdMGH
");
$queryOrms->execute([
    ":patIdRVH" => $patientIdRVH,
    ":patIdMGH" => $patientIdMGH
]);

$patInfo = $queryOrms->fetchAll()[0] ?? NULL;

if($patInfo === NULL) exit("Patient not found");

#create the message in the patient's prefered language
$smsAlertNum = $patInfo["SMSAlertNum"];
$language = $patInfo["LanguagePreference"];

if($language === "French") {
    $message = "Teleconsultation CUSM: $zoomLink. Clickez sur le lien pour connecter avec $resName";
}
else {
    $message = "MUHC teleconsultation: $zoomLink. Use this link to connect with $resName.";
}

#send sms
Sms::sendSms($smsAlertNum,$message);

echo "message should have been sent...";

?>
