<?php
//==================================================================================== 
// dischargePatient.php - php code to discharge a medivisit patient 
//==================================================================================== 
include_once("config_screens.php");

$link = mssql_connect(ARIA_DB, ARIA_USERNAME, ARIA_PASSWORD);
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, WAITROOM_DB);
$row  = "";

// Extract the webpage parameters
$verbose		= $_GET["verbose"];
$CheckoutVenue 		= $_GET["CheckoutVenue"];
$PatientId		= $_GET["PatientId"];
$AppointmentSerNum 	= $_GET["ScheduledActivitySer"];
$Final			= $_GET["Final"]; # if this is the final checkout of the day, then
					  # the patient should not be checked in to his/her
					  # remaining appointments 
if (!$link) {
    die('Something went wrong while connecting to MSSQL');
}

//======================================================================================
// Check the patient out of this Medivisit appointment
//======================================================================================
// Check the PatientLocation table to get the revision count
$PatientLocationSerNum;
$PatientLocationRevCount;
$CheckinVenueName;
$ArrivalDateTime;

$sqlMV_checkCheckin = "
  SELECT DISTINCT 
    PatientLocation.PatientLocationSerNum,
    PatientLocation.PatientLocationRevCount,
    PatientLocation.CheckinVenueName,
    PatientLocation.ArrivalDateTime
  FROM
    PatientLocation
  WHERE
    PatientLocation.AppointmentSerNum = $AppointmentSerNum
";

if($verbose){echo "<p>sqlMV_checkCheckin: $sqlMV_checkCheckin<br>";}

/* Process results */
$result = $conn->query($sqlMV_checkCheckin);

if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {

       	$PatientLocationSerNum	= $row["PatientLocationSerNum"];
    	$PatientLocationRevCount= $row["PatientLocationRevCount"];
    	$CheckinVenueName	= $row["CheckinVenueName"]; 
    	$ArrivalDateTime	= $row["ArrivalDateTime"];
    }
} else {
    die("Doesn't seem like the patient is checked in...");
}

if($verbose){echo "PatientLocationSerNum: $PatientLocationSerNum<br>";}
if($verbose){echo "PatientLocationRevCount: $PatientLocationRevCount<br>";}
if($verbose){echo "CheckinVenueName: $CheckinVenueName<br>";}
if($verbose){echo "ArrivalDateTime: $ArrivalDateTime<br>";}

// update the MH table for the last checkin
if($verbose){echo "inserting into MH table";}

$sql_insert_previousCheckin= "INSERT INTO PatientLocationMH(PatientLocationSerNum,PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime) VALUES ('$PatientLocationSerNum','$PatientLocationRevCount','$AppointmentSerNum','$CheckinVenueName','$ArrivalDateTime')";
if($verbose){echo "sql_insert_previousCheckin: $sql_insert_previousCheckin";}

$result = $conn->query($sql_insert_previousCheckin);

// remove appointment from PatientLocation table
if($verbose){echo "deleting existing entry in PatientLocation table<br>";}
$sql_delete_previousCheckin= "DELETE FROM PatientLocation WHERE PatientLocationSerNum=$PatientLocationSerNum";

$result = $conn->query($sql_delete_previousCheckin);

if($verbose){echo "deleted...<b>";}


// update MediVisitAppointmentList table to show that appointment is no longer open
if($verbose){echo "Udating MediVisitAppointmentList table to set status of appointment as Completed<br>";}

$sql_closeApt = "UPDATE MediVisitAppointmentList SET Status = 'Completed' WHERE AppointmentSerNum = '$AppointmentSerNum'";
$result = $conn->query($sql_closeApt);

if($verbose){echo "Updated...<br>";}


# Exit now without checking in for further appointments if $Final was called
if($Final==1)
{
  if($verbose){echo "Got a final so will exit now...<br>";}
  exit(0);
}

//#############################################################################################
//### At this stage the patient has been discarged from the specified Medivisit appointment ###
//#############################################################################################
// As part of the discharge, we want to check the patient in for all other appointments again but
// put the patient back into the waiting room that is appropriate for their next appointment

$CheckInURL_raw = "http://172.26.66.41/devDocuments/screens/php/checkInPatientAriaMedi.php?CheckinVenue=$CheckoutVenue&PatientId=$PatientId";
$CheckInURL = str_replace(' ', '%20', $CheckInURL_raw);

# since a script exists for this, best to call it here rather than rewrite the wheel
if($verbose){echo "CheckInURL: $CheckInURL<br>";}
$lines = file($CheckInURL);


?>


