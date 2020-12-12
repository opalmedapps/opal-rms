<?php
//====================================================================================
// getSimilarCheckins.php - php code to check if there are already patients checked in
// with similar identifiers
//====================================================================================
require("loadConfigs.php");

// Extract the webpage parameters
$firstName = $_GET["firstName"];
$lastNameFirstThree = $_GET["lastNameFirstThree"];
$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"];

//======================================================================================
// MediVisit/MySQL patients
//======================================================================================
// Create MySQL DB connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

$queryMV = $dbWRM->prepare("
    SELECT
        COUNT(DISTINCT Patient.SSN) AS numSimilarNames
    FROM
        PatientLocation
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.AppointmentSerNum = PatientLocation.AppointmentSerNum
        INNER JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
            AND Patient.FirstName = :first
            AND Patient.SSN LIKE :last
            AND (Patient.PatientId != :idRVH OR Patient.PatientId_MGH != :idMGH)
    WHERE
        PatientLocation.ArrivalDateTime >= CURDATE()
");

$queryMV->execute([
    ":first" => $firstName,
    ":last" => "$lastNameFirstThree%",
    ":idRVH" => $patientIdRVH,
    ":idMGH" => $patientIdMGH
]);

/* Process results */
$rowMV = $queryMV->fetch();

if(!$rowMV)
{
    die('Query failed.');
}

$numMV = $rowMV[0];

$dbWRM = null;

$totalSimilarNames = $numMV;

# spit the result out to the webpage/calling function
echo $totalSimilarNames;

?>
