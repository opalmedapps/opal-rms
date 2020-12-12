<?php
//script to insert patient measurements in the WRM database

require("loadConfigs.php");

//get webpage parameters
$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"];
$ssnFirstThree = $_GET["ssnFirstThree"];
$height = $_GET["height"];
$weight = $_GET["weight"];
$bsa = $_GET["bsa"];
$appointmentId = $_GET["appointmentId"];

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//first check if the patient exists in the WRM db
$sqlPatientExists = "
    SELECT
        Patient.PatientSerNum
    FROM
        Patient
    WHERE
        Patient.PatientId = '$patientIdRVH'
        AND Patient.PatientId_MGH = '$patientIdMGH'";

$queryPatientExists = $dbWRM->query($sqlPatientExists);

$row = $queryPatientExists->fetchAll(PDO::FETCH_ASSOC);
$patientSer = $row[0]['PatientSerNum'] ?? NULL;

if(!$patientSer) {echo "No patient serial!"; exit;}

$sqlWeightInsert = "
    INSERT INTO PatientMeasurement (PatientSer,Date,Time,Height,Weight,BSA,AppointmentId,PatientId)
    VALUES ($patientSer,CURDATE(),CURTIME(),$height,$weight,$bsa,'$appointmentId','$patientIdRVH')";

$queryWeightInsert = $dbWRM->exec($sqlWeightInsert);

if($queryWeightInsert) {echo "Measurements inserted!";}
else {echo "Failure";}

$dbWRM = null;

?>
