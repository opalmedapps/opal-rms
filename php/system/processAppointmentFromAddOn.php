<?php declare(strict_types=1);
#---------------------------------------------------------------------------------------------------------------
# Script that parses a POST request from the add on page and inserts/updates the appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#load global configs
require __DIR__."/../../vendor/autoload.php";

use GuzzleHttp\Client;

use Orms\Config;

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode("Non POST requests not supported");
    exit;
}

#process post request
$postParams = getPostContents();
$postParams = utf8_decode_recursive($postParams);

$appointmentInfo = [];
foreach($postParams as $key => $val) {
    $appointmentInfo[$key] = !empty($val) ? $val : NULL;
}

#call the importer script
$url = Config::getApplicationSettings()->environment->baseUrl ."/php/system/processAppointmentFromMedivisit";

$client = new Client();
$request = $client->request("POST",$url,[
    "form_params" => $appointmentInfo
]);

$requestCode = $request->getStatusCode();
$requestResult = $request->getBody()->getContents();

if($requestCode === 200 && isset($appointmentInfo["PatientId"])) {
    checkInPatientForAddOn($appointmentInfo["PatientId"]);
}

echo $requestResult;

###################################
# Functions
###################################

#temp function to check in a patient after their add on has been created
function checkInPatientForAddOn(string $patId): void
{
    $url = Config::getApplicationSettings()->environment->baseUrl;
    (new Client())->request("GET",$url ."/php/system/checkInPatientAriaMedi.php",[
        "query" => [
            "CheckinVenue" => "ADDED ON BY RECEPTION",
            "PatientId" => $patId
        ]
    ]);
}

?>
