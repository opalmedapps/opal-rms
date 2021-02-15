<?php declare(strict_types=1);
//====================================================================================
// php code to query the database and extract the list of patients
// who are currently checked in for open appointments today, using a selected list of destination rooms/areas
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

//connect to databases
$dbh = Database::getOrmsConnection();

$checkinVenue = $_GET["checkinVenue"];

if($checkinVenue)
{
    $checkinVenue_list = explode(",", $checkinVenue);
    $checkinVenue_list = "'" . implode("','", $checkinVenue_list) . "'";
}
else
{
    echo "[]";
    exit;
}

$query = $dbh->prepare("
    SELECT DISTINCT
        Venues.AriaVenueId AS LocationId,
        PL.ArrivalDateTime,
        COALESCE(P.PatientSerNum,'Nobody') AS PatientId,
        P.PatientId AS Mrn
    FROM
    (
        SELECT IntermediateVenue.AriaVenueId
        FROM IntermediateVenue
        WHERE  IntermediateVenue.AriaVenueId IN ($checkinVenue_list)
        UNION
        SELECT ExamRoom.AriaVenueId
        FROM ExamRoom
        WHERE  ExamRoom.AriaVenueId IN ($checkinVenue_list)
    ) AS Venues
    LEFT JOIN PatientLocation PL ON PL.CheckinVenueName = Venues.AriaVenueId
    LEFT JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PL.AppointmentSerNum
    LEFT JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
    WHERE
        (DATE(PL.ArrivalDateTime) = CURDATE() OR PL.ArrivalDateTime IS NULL)
");
$query->execute();

# Remove last two lines so that rooms show up regardless of date - this would be necessary
# if rooms are left open at the end of the day. However a cron job at midnight should checkout
# all remaining checked in patients. Showing rooms that are still checked in from yesterday
# is not very useful within the interface as these patients cannot be moved back into the waiting
# room as that would mean checking them in for today, when their appointments were actually yesterday
# Best solution is a cron job to checkout all checked-in patients at midnight

$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);

?>
