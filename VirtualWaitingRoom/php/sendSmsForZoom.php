<?php declare(strict_types = 1);

#====================================================================================
# php code to send an SMS message to a given patient using their cell number
# registered in the ORMS database
#====================================================================================
require("loadConfigs.php");

# Extract the webpage parameters

$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"] ?? "";
$zoomLink     = $_GET["zoomLink"];
$resName      = $_GET["resName"];

#find the patient in ORMS
$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);
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
$language = $patInfo["LanguagePreference"];
if($language === "French") {
    $message = "Teleconsultation CUSM: $zoomLink. Clickez sur le lien pour connecter avec $resName";
}
else {
    $message = "MUHC teleconsultation: $zoomLink. Use this link to connect with $resName.";
}

#send sms
$SMS_licencekey = SMS_licencekey;
$SMS_gatewayURL = SMS_gatewayURL;
$smsAlertNum	= $patInfo["SMSAlertNum"];

$SMS = "$SMS_gatewayURL?PhoneNumber=$smsAlertNum&Message=$message&LicenseKey=$SMS_licencekey";

echo "SMS: $SMS<br>";

$SMS = str_replace(' ', '%20', $SMS);
$SMS_response = file_get_contents($SMS);

echo "message should have been sent...";

?>
