<?php

declare(strict_types=1);
//====================================================================================
// php code to check if there are already patients checked in
// with similar identifiers
//====================================================================================

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;

$params = Http::getRequestContents();

$patientId = $params["patientId"];

$query = Database::getOrmsConnection()->prepare("
    SELECT
        COUNT(*) AS numSimilarNames
    FROM
        PatientLocation PL
        INNER JOIN Patient P ON P.PatientSerNum = :pid
        INNER JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PL.AppointmentSerNum
        INNER JOIN Patient Similar ON Similar.PatientSerNum = MV.PatientSerNum
            AND Similar.PatientSerNum != P.PatientSerNum
            AND Similar.FirstName = P.FirstName
            AND Similar.LastName LIKE CONCAT(SUBSTR(P.LastName,1,3),'%')
    WHERE
        PL.ArrivalDateTime >= CURDATE()
");

$query->execute([
    ":pid" => $patientId
]);

$similarNames = $query->fetchAll()[0]["numSimilarNames"] ?? 0;

Http::generateResponseJsonAndExit(200,data: (int) $similarNames);
