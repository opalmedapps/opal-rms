<?php
//====================================================================================
// getSimilarCheckins.php - php code to check if there are already patients checked in
// with similar identifiers
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

require("loadConfigs.php");

use Orms\Config;

// Extract the webpage parameters
$firstName = $_GET["firstName"];
$lastNameFirstThree = $_GET["lastNameFirstThree"];
$patientId = $_GET["patientId"];

//======================================================================================
// MediVisit/MySQL patients
//======================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

$query = $dbh->prepare("
    SELECT
        COUNT(DISTINCT Patient.SSN) AS numSimilarNames
    FROM
        PatientLocation
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.AppointmentSerNum = PatientLocation.AppointmentSerNum
        INNER JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
            AND Patient.FirstName = :first
            AND Patient.SSN LIKE :last
            AND Patient.PatientSerNum != :pSer
    WHERE
        PatientLocation.ArrivalDateTime >= CURDATE()
");

$query->execute([
    ":first" => $firstName,
    ":last" => "$lastNameFirstThree%",
    ":pSer" => $patientId
]);

/* Process results */
$rowMV = $query->fetch();

if(!$rowMV)
{
    die('Query failed.');
}

$numMV = $rowMV[0];

$totalSimilarNames = $numMV;

# spit the result out to the webpage/calling function
echo $totalSimilarNames;

?>
