<?php declare(strict_types=1);
//====================================================================================
// checkinPatientMV.php - php code to check a Medivisit patient into Mysql
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Database;

// Create DB connection
$dbh = Database::getOrmsConnection();

// Extract the webpage parameters
$checkinVenue = utf8_decode_recursive($_GET["checkinVenue"]);
$appointmentSerNum = $_GET["scheduledActivitySer"];
$intendedAppointment = $_GET["intendedAppointment"];

echo "CheckinVenue: $checkinVenue<br>";
echo "AppointmentSerNum: $appointmentSerNum<br>";

#---------------------------------------------------------------------------------------------
# First, check for an existing entry in the patient location table for this appointment
#---------------------------------------------------------------------------------------------
$queryCheckCheckin = $dbh->prepare("
    SELECT DISTINCT
        PatientLocation.PatientLocationSerNum,
        PatientLocation.PatientLocationRevCount,
        PatientLocation.CheckinVenueName,
        PatientLocation.ArrivalDateTime,
        PatientLocation.IntendedAppointmentFlag,
        PatientLocationMH.PatientLocationSerNum AS MHSerNum
    FROM
        PatientLocation
        LEFT JOIN PatientLocationMH ON PatientLocationMH.PatientLocationSerNum = PatientLocation.PatientLocationSerNum
    WHERE
        PatientLocation.AppointmentSerNum = $appointmentSerNum
");
$queryCheckCheckin->execute();

$row = $queryCheckCheckin->fetchAll()[0] ?? [];

$patientLocationSerNum = $row["PatientLocationSerNum"] ?? NULL;
$patientLocationRevCount = $row["PatientLocationRevCount"] ?? 0;
$checkinVenueName = $row["CheckinVenueName"] ?? NULL;
$arrivalDateTime = $row["ArrivalDateTime"] ?? NULL;
$intendedFlag = $row["IntendedAppointmentFlag"] ?? NULL;
$mhSerNum = $row["MHSerNum"] ?? NULL;



echo "PatientLocationSerNum: $patientLocationSerNum<br>";
echo "PatientLocationRevCount: $patientLocationRevCount<br>";
echo "CheckinVenueName: $checkinVenueName<br>";
echo "ArrivalDateTime: $arrivalDateTime<br>";

#---------------------------------------------------------------------------------------------
# If there is an existing entry in the patient location table, take the values and
# insert them into the PatientLocationMH table
# only do this if the PatientLocation row wasn't already inserted
#---------------------------------------------------------------------------------------------
if($patientLocationSerNum and !$mhSerNum)
{
    echo "inserting into MH table";
    $sql_insert_previousCheckin = "
        INSERT INTO PatientLocationMH(PatientLocationSerNum,PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime,IntendedAppointmentFlag)
        VALUES ('$patientLocationSerNum','$patientLocationRevCount','$appointmentSerNum','$checkinVenueName','$arrivalDateTime','$intendedFlag')";

    //echo "sql_insert_previousCheckin: $sql_insert_previousCheckin";

    $dbh->query($sql_insert_previousCheckin);
}

#---------------------------------------------------------------------------------------------
# Put an entry into the PatientLocation table
# - first time entry the RevCount = 0, increment it to 1
# - not first time entry, increment by one the RevCount of the previous entry
#---------------------------------------------------------------------------------------------
$patientLocationRevCount++;
$sql_insert_newCheckin = "
    INSERT INTO PatientLocation(PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime,IntendedAppointmentFlag)
    VALUES ('$patientLocationRevCount','$appointmentSerNum','$checkinVenue',NOW(),'$intendedAppointment')";

//echo "sql_insert_newCheckin: $sql_insert_newCheckin<br>";

$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT);

if($dbh->query($sql_insert_newCheckin))
{
    $checkinStatus = "OK";
}
else
{
    $checkinStatus = "Unable to check in";
}

$dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

echo "CheckinStatus: $checkinStatus... ";

# if there was an existing entry in the patient location table, delete it now
if($patientLocationSerNum)
{
    echo "deleting existing entry in PatientLocation table<br>";
    $sql_delete_previousCheckin = "
        DELETE FROM PatientLocation
        WHERE PatientLocationSerNum = $patientLocationSerNum";

    $dbh->query($sql_delete_previousCheckin);

    echo "deleted...<b>";
}

?>
