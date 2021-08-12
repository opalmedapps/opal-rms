<?php

declare(strict_types=1);
//====================================================================================
// php code to check if there are already patients checked in
// with similar identifiers
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\DataAccess\Database;

$firstName          = $_GET["firstName"];
$lastNameFirstThree = $_GET["lastNameFirstThree"];
$patientId          = $_GET["patientId"];

$dbh = Database::getOrmsConnection();

$query = $dbh->prepare("
    SELECT
        COUNT(*) AS numSimilarNames
    FROM
        PatientLocation PL
        INNER JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PL.AppointmentSerNum
        INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
            AND P.FirstName = :first
            AND P.LastName LIKE :last
            AND P.PatientSerNum != :pSer
    WHERE
        PL.ArrivalDateTime >= CURDATE()
");

$query->execute([
    ":first" => $firstName,
    ":last" => "$lastNameFirstThree%",
    ":pSer" => $patientId
]);

echo $query->fetchAll()[0]["numSimilarNames"] ?? 0;
