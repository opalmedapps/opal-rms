<?php

declare(strict_types=1);
//====================================================================================
// php code to query the database and extract the list of patients
// who are currently checked in for open appointments today, using a selected list of destination rooms/areas
//====================================================================================

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$examRooms = $params["examRooms"] ?? [];

if($examRooms === []) {
    Http::generateResponseJsonAndExit(200, data: []);
}

$sql = "
    SELECT DISTINCT
        ER.AriaVenueId AS LocationId,
        PL.ArrivalDateTime,
        COALESCE(P.PatientSerNum,'Nobody') AS PatientId,
        CONCAT(P.LastName,', ',P.FirstName) AS Name
    FROM
        ExamRoom ER
        LEFT JOIN PatientLocation PL ON PL.CheckinVenueName = ER.AriaVenueId
            AND (
                DATE(PL.ArrivalDateTime) = CURDATE()
                OR PL.ArrivalDateTime IS NULL
            )
        LEFT JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PL.AppointmentSerNum
        LEFT JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
    WHERE
        :roomList:
";

$sqlStringExam = Database::generateBoundedSqlString($sql, ":roomList:", "ER.AriaVenueId", $examRooms);

$query = Database::getOrmsConnection()->prepare($sqlStringExam["sqlString"]);
$query->execute($sqlStringExam["boundValues"]);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($query->fetchAll()));
