<?php
//====================================================================================
// YM 2018-05-04
// Get the list of questionnaires that the patient have answered only
//====================================================================================
#function debuglog($wsTxt) {
#  $myfile = file_put_contents('debug.log', $wsTxt.PHP_EOL , FILE_APPEND | LOCK_EX);
#}

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

// Create DB connection
$conn = Config::getDatabaseConnection("QUESTIONNAIRE");

// Extract the webpage parameters
$wsPatientID = $_GET["mrn"];
$crossDbName = Config::getConfigs("database")["OPAL_DB"];

// Prepare the query to fetch only records that have been completed by the patients
// Flag questionnaires that had been completed within 3 weeks (Will change this logic later)

$result = $conn->query("CALL getQuestionnaireListORMS('$wsPatientID', '$crossDbName')");

$json = utf8_encode_recursive($result->fetchAll());
echo json_encode($json);

?>
