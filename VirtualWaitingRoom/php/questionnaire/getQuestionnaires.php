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
use Orms\Opal;

$mrn = $_GET["mrn"] ?? NULL;

if($mrn === NULL) {
    http_response_code(400);
    exit("Empty mrn!");
}

echo json_encode(Encoding::utf8_encode_recursive(Opal::getListOfQuestionnairesForPatient($mrn)));

?>
