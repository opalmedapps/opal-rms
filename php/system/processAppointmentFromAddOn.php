<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a POST request from the add on page and inserts/updates the appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode("Non POST requests not supported");
    exit;
}

#process post request
$postParams = getPostContents();

$appointmentInfo = [];
foreach($postParams as $key => $val) {
    $appointmentInfo[$key] = !empty($val) ? $val : NULL;
}

#call the importer script
$url = Config::getConfigs("path")["BASE_URL"]."/php/system/processAppointmentFromMedivisit";

$ch = curl_init();
curl_setopt_array($ch,[
    CURLOPT_URL             => $url,
    CURLOPT_POSTFIELDS      => $appointmentInfo,
    CURLOPT_RETURNTRANSFER  => TRUE
]);
$requestResult = curl_exec($ch);
$resultCode = curl_getinfo($ch)["http_code"];
curl_close($ch);

if($resultCode === 200) {
    checkInPatientForAddOn($appointmentInfo["PatientId"]);
}

echo $requestResult;

exit;

###################################
# Functions
###################################

#temp function to check in a patient after their add on has been created
function checkInPatientForAddOn(string $patId): void
{
    $path = Config::getConfigs("path");
    $sciptLocation = $path["BASE_URL"] ."/php/system/checkInPatientAriaMedi.php?CheckinVenue=ADDED ON BY RECEPTION&PatientId=$patId";
    $sciptLocation = str_replace(' ','%20',$sciptLocation);
    file_get_contents($sciptLocation);
}

?>
