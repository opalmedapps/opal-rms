<?php
//====================================================================================
// php code to check a patient into Aria
//====================================================================================
include_once("SystemLoader.php");

$link = Config::getDatabaseConnection("ARIA");

// Extract the webpage parameters
$CheckinVenue           = !empty($_GET["CheckinVenue"]) ? $_GET["CheckinVenue"] : NULL;
$ScheduledActivitySer   = !empty($_GET["ScheduledActivitySer"]) ? $_GET["ScheduledActivitySer"] : NULL;
$location               = !empty($_GET["location"]) ? $_GET["location"] : NULL;

if (!$link) {
    die('Something went wrong while connecting to MSSQL');
}

$sql = "
declare @var INT

SET @var = (SELECT
Venue.ResourceSer AS ResourceSer
FROM
variansystem.dbo.Venue Venue
WHERE
Venue.VenueId='$CheckinVenue')

exec variansystem.dbo.vp_CheckInPatient @nVenueLocationSer = @var, @nScheduledActivitySer = $ScheduledActivitySer, @strComment = null, @strHstryUserName=N'DS1_1'

";

echo "SQL: $sql<br>";

$query = $link->query($sql);

/* Process results */
$row = $query->fetch();
#echo "row: $row[1]<br>";

if (!$query) {
    die('Query failed.');
}

?>
