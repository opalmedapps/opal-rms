<?php declare(strict_types=1);
//script to insert patient measurements in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

//get webpage parameters
$patientId = $_GET["patientId"];
$ssnFirstThree = $_GET["ssnFirstThree"];
$height = $_GET["height"];
$weight = $_GET["weight"];
$bsa = $_GET["bsa"];
$appointmentId = $_GET["appointmentId"];

//connect to db
$dbh = Config::getDatabaseConnection("ORMS");

//get the current mrn of the patient as a security check
$queryPatientExists = $dbh->prepare("
    SELECT
        Patient.PatientId
    FROM
        Patient
    WHERE
        Patient.PatientSerNum = :pSer
");
$queryPatientExists->execute([":pSer" => $patientId]);

$mrn = $queryPatientExists->fetchAll()[0]["PatientId"] ?? NULL;

if($mrn === NULL) {echo "No patient mrn!"; exit;}

$queryWeightInsert = $dbh->prepare("
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
