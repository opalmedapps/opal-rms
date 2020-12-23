<?php declare(strict_types = 1);
//====================================================================================
// php code to insert patients' cell phone numbers and language preferences
// into ORMS
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

require("loadConfigs.php");

use Orms\Config;

#extract the webpage parameters
$patientId          = $_GET["patientId"] ?? NULL;
$user               = $_GET["user"] ?? NULL;

$dbh = Config::getDatabaseConnection("ORMS");
$dbh->prepare("
    INSERT INTO TEMP_PatientQuestionnaireReview(PatientSer,User)
    VALUES(:pSer,:user)"
)->execute([
    ":pSer"   => $patientId,
    ":user"   => $user
]);

echo "Record inserted";

?>
