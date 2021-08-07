<?php declare(strict_types=1);
//====================================================================================
// php code to query the database and extract the list of patients
// who are currently checked in for open appointments today, using a selected list of destination rooms/areas
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;

$checkinVenueList = $_GET["checkinVenue"] ?? NULL;

if($checkinVenueList === NULL) {
    echo "[]";
    exit;
}

$checkinVenueList = explode(",", $checkinVenueList);

$sql = "
    SELECT DISTINCT
        Venues.AriaVenueId AS LocationId,
        PL.ArrivalDateTime,
        COALESCE(P.PatientSerNum,'Nobody') AS PatientId,
        CONCAT(P.LastName,', ',P.FirstName) AS Name
    FROM
        (
            SELECT
                IV.AriaVenueId
            FROM
                IntermediateVenue IV
            WHERE
                :roomListVenue:
            UNION
            SELECT
                ER.AriaVenueId
            FROM
                ExamRoom ER
            WHERE
                :roomListExam:
        ) AS Venues
        LEFT JOIN PatientLocation PL ON PL.CheckinVenueName = Venues.AriaVenueId
        LEFT JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PL.AppointmentSerNum
        LEFT JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
    WHERE
        DATE(PL.ArrivalDateTime) = CURDATE()
        OR PL.ArrivalDateTime IS NULL
";

$sqlStringVenue = Database::generateBoundedSqlString($sql,":roomListVenue:","IV.AriaVenueId",$checkinVenueList);
$sqlStringExam = Database::generateBoundedSqlString($sqlStringVenue["sqlString"],":roomListExam:","ER.AriaVenueId",$checkinVenueList);

$query = Database::getOrmsConnection()->prepare($sqlStringExam["sqlString"]);
$query->execute(array_merge($sqlStringVenue["boundValues"],$sqlStringExam["boundValues"]));

# Remove last two lines so that rooms show up regardless of date - this would be necessary
# if rooms are left open at the end of the day. However a cron job at midnight should checkout
# all remaining checked in patients. Showing rooms that are still checked in from yesterday
# is not very useful within the interface as these patients cannot be moved back into the waiting
# room as that would mean checking them in for today, when their appointments were actually yesterday
# Best solution is a cron job to checkout all checked-in patients at midnight

$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);
