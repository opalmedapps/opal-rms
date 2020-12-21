<?php
//script to insert patient measurements in the WRM database

require("../loadConfigs.php");

//get webpage parameters
$patientId = $_GET["patientId"];
$ssnFirstThree = $_GET["ssnFirstThree"];
$height = $_GET["height"];
$weight = $_GET["weight"];
$bsa = $_GET["bsa"];
$appointmentId = $_GET["appointmentId"];

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//get the current mrn of the patient as a security check
$queryPatientExists = $dbWRM->prepare("
    SELECT
        Patient.PatientId
    FROM
        Patient
    WHERE
        Patient.PatientSerNum = :pSer
");
$queryPatientExists->execute([":pSer" => $patientId]);

$mrn = $queryPatientExists->fetchAll(PDO::FETCH_ASSOC)[0]["PatientId"] ?? NULL;

if($mrn === NULL) {echo "No patient mrn!"; exit;}

$queryWeightInsert = $dbWRM->prepare("
    INSERT INTO PatientMeasurement (PatientSer,Date,Time,Height,Weight,BSA,AppointmentId,PatientId)
    VALUES (:pSer,CURDATE(),CURTIME(),:height,:weight,:bsa,:appId,:mrn)
");
$queryWeightInsert->execute([
    ":pSer"     => $patientId,
    ":height"   => $height,
    ":weight"   => $weight,
    ":bsa"      => $bsa,
    ":appId"    => $appointmentId,
    ":mrn"      => $mrn
]);

echo "Measurements inserted!";

?>
