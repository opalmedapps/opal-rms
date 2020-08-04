<?php
//====================================================================================
// completeAppointment.php - php code to discharge a medivisit patient
//====================================================================================
require("loadConfigs.php");

$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Extract the webpage parameters
$checkoutVenue = $_GET["checkoutVenue"];
$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"];
$appointmentSerNum = $_GET["scheduledActivitySer"];
$final = $_GET["final"];	# if this is the final checkout of the day, then
				# the patient should not be checked in to his/her
				# remaining appointments

//======================================================================================
// Check the patient out of this Medivisit appointment
//======================================================================================
// Check the PatientLocation table to get the revision count
$patientLocationSerNum;
$patientLocationRevCount;
$checkinVenueName;
$arrivalDateTime;

$sqlMV_checkCheckin = "
	SELECT DISTINCT
		PatientLocation.PatientLocationSerNum,
		PatientLocation.PatientLocationRevCount,
		PatientLocation.CheckinVenueName,
		PatientLocation.ArrivalDateTime
	FROM
		PatientLocation
	WHERE
		PatientLocation.AppointmentSerNum = $appointmentSerNum";

/* Process results */
$result = $dbWRM->query($sqlMV_checkCheckin);

$rows = $result->fetchAll(PDO::FETCH_ASSOC);

if(count($rows) > 0)
{
	// output data of each row
	foreach($rows as &$row)
	{
		$patientLocationSerNum = $row["PatientLocationSerNum"];
		$patientLocationRevCount = $row["PatientLocationRevCount"];
		$checkinVenueName = $row["CheckinVenueName"];
		$arrivalDateTime = $row["ArrivalDateTime"];
	}
}
else
{
	die("Doesn't seem like the patient is checked in...");
}


// update the MH table for the last checkin

$queryInsertPreviousCheckIn = $dbWRM->prepare("
    INSERT INTO PatientLocationMH(PatientLocationSerNum,PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime,IntendedAppointmentFlag)
    VALUES (?,?,?,?,?,1)"
);
$queryInsertPreviousCheckIn->execute([$patientLocationSerNum,$patientLocationRevCount,$appointmentSerNum,$checkinVenueName,$arrivalDateTime]);

// remove appointment from PatientLocation table
$sql_delete_previousCheckin= "DELETE FROM PatientLocation WHERE PatientLocationSerNum= $patientLocationSerNum";

$result = $dbWRM->query($sql_delete_previousCheckin);


// update MediVisitAppointmentList table to show that appointment is no longer open
$sql_closeApt = "UPDATE MediVisitAppointmentList SET Status = 'Completed' WHERE AppointmentSerNum = '$appointmentSerNum'";
$result = $dbWRM->query($sql_closeApt);

# Exit now without checking in for further appointments if $Final was called
if($final == 1)
{
	$dbWRM = null;
	exit(0);
}

//#############################################################################################
//### At this stage the patient has been discharged from the specified appointment ###
//#############################################################################################
// As part of the discharge, we want to check the patient in for all other appointments again but
// put the patient back into the waiting room that is appropriate for their next appointment

$base_url = BASE_URL;

$checkinURL_raw = "$base_url/php/checkinPatientAriaMedi.php?checkinVenue=$checkoutVenue&patientIdRVH=$patientIdRVH&patientIdMGH=$patientIdMGH";
$checkinURL = str_replace(' ','%20',$checkinURL_raw);

# since a script exists for this, best to call it here rather than rewrite the wheel
$lines = file($checkinURL);

$dbWRM = null;

?>
