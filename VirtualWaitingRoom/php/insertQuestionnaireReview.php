<?php declare(strict_types = 1);
//====================================================================================
// php code to insert patients' cell phone numbers and language preferences
// into ORMS
//====================================================================================
require("loadConfigs.php");

#extract the webpage parameters
$patientIdRVH       = $_GET["patientIdRVH"] ?? NULL;
$patientIdMGH       = $_GET["patientIdMGH"] ?? "";
$user               = $_GET["user"] ?? NULL;

#find the patient in ORMS
$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);
$queryOrms = $dbh->prepare("
    SELECT
        Patient.PatientSerNum
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

$patSer = $queryOrms->fetchAll()[0]["PatientSerNum"] ?? NULL;

if($patSer === NULL) exit("Patient not found");

#set the patient phone number
$queryInsert = $dbh->prepare("
    INSERT INTO TEMP_PatientQuestionnaireReview(PatientSer,User)
    VALUES(:patSer,:user)"
);
$queryInsert->execute([
    ":patSer" => $patSer,
    ":user"   => $user
]);

echo "Record inserted";

?>
