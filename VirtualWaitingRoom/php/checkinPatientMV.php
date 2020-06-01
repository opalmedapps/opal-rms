<?php
//====================================================================================
// checkinPatientMV.php - php code to check a Medivisit patient into Mysql
//====================================================================================
require("loadConfigs.php");

// Create DB connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Extract the webpage parameters
$checkinVenue = utf8_decode_recursive($_GET["checkinVenue"]);
$appointmentSerNum = $_GET["scheduledActivitySer"];
$intendedAppointment = $_GET["intendedAppointment"];

echo "CheckinVenue: $checkinVenue<br>";
echo "AppointmentSerNum: $appointmentSerNum<br>";

#---------------------------------------------------------------------------------------------
# First, check for an existing entry in the patient location table for this appointment
#---------------------------------------------------------------------------------------------
$patientLocationSerNum;
$patientLocationRevCount;
$checkinVenueName;
$arrivalDateTime;

$sqlMV_checkCheckin = "
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
		PatientLocation.AppointmentSerNum = $appointmentSerNum";

//echo "<p>sqlMV_checkCheckin: $sqlMV_checkCheckin<br>";

/* Process results */
$query = $dbWRM->query($sqlMV_checkCheckin);

$rows = $query->fetchAll(PDO::FETCH_ASSOC);

if(count($rows) > 0)
{
	// output data of each row
	foreach($rows as &$row)
	{
		$patientLocationSerNum = $row["PatientLocationSerNum"];
		$patientLocationRevCount = $row["PatientLocationRevCount"];
		$checkinVenueName = $row["CheckinVenueName"];
		$arrivalDateTime = $row["ArrivalDateTime"];
		$intendedFlag = $row["IntendedAppointmentFlag"];
		$mhSerNum = $row["MHSerNum"];
	}
}
else
{
	echo "Patient not already checked in for this appointment... proceeding to check in";
}

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

	$result = $dbWRM->query($sql_insert_previousCheckin);
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

$dbWRM->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT);

if($dbWRM->query($sql_insert_newCheckin))
{
	$checkinStatus = "OK";
}
else
{
	$checkinStatus = "Unable to check in";
}

$dbWRM->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

echo "CheckinStatus: $checkinStatus... ";

# if there was an existing entry in the patient location table, delete it now
if($patientLocationSerNum)
{
	echo "deleting existing entry in PatientLocation table<br>";
	$sql_delete_previousCheckin = "
		DELETE FROM PatientLocation
		WHERE PatientLocationSerNum = $patientLocationSerNum";

	$result = $dbWRM->query($sql_delete_previousCheckin);

	echo "deleted...<b>";
}

// Close the MySQL connection
$dbWRM = null;
?>
