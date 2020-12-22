<?php declare(strict_types = 1);
//====================================================================================
// php code to insert patients' cell phone numbers and language preferences
// into ORMS
//====================================================================================
require("loadConfigs.php");

#extract the webpage parameters
$patientId          = $_GET["patientId"] ?? NULL;
$user               = $_GET["user"] ?? NULL;

$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);
$dbh->prepare("
    INSERT INTO TEMP_PatientQuestionnaireReview(PatientSer,User)
    VALUES(:pSer,:user)"
)->execute([
    ":pSer"   => $patientId,
    ":user"   => $user
]);

echo "Record inserted";

?>
