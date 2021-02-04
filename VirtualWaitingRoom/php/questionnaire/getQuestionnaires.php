<?php declare(strict_types=1);
//====================================================================================
// YM 2018-05-04
// Get the list of questionnaires that the patient have answered only
//====================================================================================
#function debuglog($wsTxt) {
#  $myfile = file_put_contents('debug.log', $wsTxt.PHP_EOL , FILE_APPEND | LOCK_EX);
#}

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;
use Orms\Database;

// Create DB connection
$conn = Database::getQuestionnaireConnection();

// Extract the webpage parameters
$wsPatientID = $_GET["mrn"];
$crossDbName = Config::getApplicationSettings()->opalDb?->databaseName;

// Prepare the query to fetch only records that have been completed by the patients
// Flag questionnaires that had been completed within 3 weeks (Will change this logic later)

if($conn !== NULL) {
    $query = $conn->prepare("CALL getQuestionnaireListORMS(?,?)");
    $query->execute([$wsPatientID,$crossDbName]);

    $json = utf8_encode_recursive($query->fetchAll());
}
else {
    $json = [];
}

echo json_encode($json);

?>
