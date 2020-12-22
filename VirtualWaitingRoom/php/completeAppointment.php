<?php
//====================================================================================
// php code to discharge a medivisit patient
//====================================================================================
require("loadConfigs.php");

$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Extract the webpage parameters
$checkoutVenue = $_GET["checkoutVenue"];
$appointmentSerNum = $_GET["scheduledActivitySer"];

//======================================================================================
// Check the patient out of this Medivisit appointment
//======================================================================================
// Check the PatientLocation table to get the revision count

$queryAppointment = $dbWRM->prepare("
    SELECT DISTINCT
        PatientLocation.PatientLocationSerNum,
        PatientLocation.PatientLocationRevCount,
        PatientLocation.CheckinVenueName,
        PatientLocation.ArrivalDateTime,
        MV.PatientSerNum
    FROM
        PatientLocation
        INNER JOIN MediVisitAppointmentList MV ON MV.AppointmentSerNum = PatientLocation.AppointmentSerNum
    WHERE
        PatientLocation.AppointmentSerNum = :appId
");
$queryAppointment->execute([":appId" => $appointmentSerNum]);

$appointment = $queryAppointment->fetchAll(PDO::FETCH_ASSOC)[0] ?? NULL;

if($appointment === NULL) {
    die("Doesn't seem like the patient is checked in...");
}

$patientLocationSerNum      = $appointment["PatientLocationSerNum"];
$patientLocationRevCount    = $appointment["PatientLocationRevCount"];
$checkinVenueName           = $appointment["CheckinVenueName"];
$arrivalDateTime            = $appointment["ArrivalDateTime"];
$patientId                  = $appointment["PatientSerNum"];

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
$sql_closeApt = "UPDATE MediVisitAppointmentList SET Status = 'Completed' WHERE AppointmentSerNum = $appointmentSerNum";
$result = $dbWRM->query($sql_closeApt);

//#############################################################################################
//### At this stage the patient has been discharged from the specified appointment ###
//#############################################################################################
// As part of the discharge, we want to check the patient in for all other appointments again but
// put the patient back into the waiting room that is appropriate for their next appointment

$base_url = BASE_URL;

$checkinURL_raw = "$base_url/php/checkinPatientAriaMedi.php?checkinVenue=$checkoutVenue&patientId=$patientId";
$checkinURL = str_replace(' ','%20',$checkinURL_raw);

# since a script exists for this, best to call it here rather than rewrite the wheel
$lines = file($checkinURL);

$dbWRM = null;

?>
