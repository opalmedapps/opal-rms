<?php declare(strict_types=1);
//====================================================================================
// YM 2018-05-04
// Get the list of questionnaires that the patient have answered only
//====================================================================================
#function debuglog($wsTxt) {
#  $myfile = file_put_contents('debug.log', $wsTxt.PHP_EOL , FILE_APPEND | LOCK_EX);
#}

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Http;
use Orms\Patient\Patient;
use Orms\Hospital\Opal;

$patientId = $_GET["patientId"] ?? NULL;

if($patientId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Empty id");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

echo json_encode(Encoding::utf8_encode_recursive(Opal::getListOfQuestionnairesForPatient($patient)));
