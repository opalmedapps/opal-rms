<?php
//====================================================================================
// php code to query the MySQL databases and extract the list of patients
// who are currently checked in for open appointments today in Medivisit (MySQL), using a selected list of destination rooms/areas
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

require("loadConfigs.php");

use Orms\Config;

//connect to databases
$dbh = Config::getDatabaseConnection("ORMS");

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

$json = [];

#-------------------------------------------------------------------------------------
# Now, get the Medivisit checkins from MySQL
#-------------------------------------------------------------------------------------
$sql = "
    SELECT DISTINCT
        Venues.AriaVenueId AS LocationId,
        PatientLocation.ArrivalDateTime,
        COALESCE(Patient.PatientSerNum,'Nobody') AS PatientId,
        Patient.PatientId AS Mrn
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
    LEFT JOIN PatientLocation ON PatientLocation.CheckinVenueName = Venues.AriaVenueId
    LEFT JOIN MediVisitAppointmentList ON MediVisitAppointmentList.AppointmentSerNum = PatientLocation.AppointmentSerNum
    LEFT JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
    WHERE
        (DATE(PatientLocation.ArrivalDateTime) = CURDATE() OR PatientLocation.ArrivalDateTime IS NULL)";

# Remove last two lines so that rooms show up regardless of date - this would be necessary
# if rooms are left open at the end of the day. However a cron job at midnight should checkout
# all remaining checked in patients. Showing rooms that are still checked in from yesterday
# is not very useful within the interface as these patients cannot be moved back into the waiting
# room as that would mean checking them in for today, when their appointments were actually yesterday
# Best solution is a cron job to checkout all checked-in patients at midnight

#echo "Medivisit query: $sql<br>";
/* Process results */
$query = $dbh->query($sql);

// output data of each row
while($row = $query->fetch())
{
    $json[] = $row;
}

$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
