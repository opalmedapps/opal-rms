<?php
//====================================================================================
// php code to check a patient into all appointments
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$dbh = Config::getDatabaseConnection("ORMS");

// Extract the webpage parameters
$checkinVenue = $_GET["checkinVenue"];
$originalAppointmentSer = $_GET["appointmentSer"];
$patientId = $_GET["patientId"];

$baseURL = Config::getConfigs("path")["BASE_URL"] ."/VirtualWaitingRoom";

$ariaURL = Config::getConfigs("aria")["ARIA_CHECKIN_URL"] ?? NULL;

//======================================================================================
// Check for upcoming appointments
//======================================================================================
$today = date("Y-m-d");
$startOfToday = "$today 00:00:00";
$endOfToday = "$today 23:59:59";

############################################################################################
######################################### Medivisit ########################################
############################################################################################
$queryApptMedivisit = $dbh->prepare("
    SELECT DISTINCT
        MediVisitAppointmentList.AppointmentSerNum,
        MediVisitAppointmentList.AppointSys
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
            AND MediVisitAppointmentList.ScheduledDateTime BETWEEN '$startOfToday' AND '$endOfToday'
            AND MediVisitAppointmentList.Status = 'Open'
    WHERE
        Patient.PatientSerNum = :pSer
    ORDER BY MediVisitAppointmentList.ScheduledDateTime
");

/* Process results */
$queryApptMedivisit->execute([":pSer" => $patientId]);

//check if the appointment that was moved in the VWR is a Medivisit appointment
//if it is, then we should indicate that the patient was checked into the room because of this appointment in the PatientLocation table
$medivisitOriginal = 0;
if(strstr($originalAppointmentSer,'Medivisit')) //check if the appointment is a Medivist one in the first place
{
    $medivisitOriginal = 1;
    $originalAppointmentSer = str_replace('Medivisit','',$originalAppointmentSer);
}

// output data of each row
foreach($queryApptMedivisit->fetchAll() as $row)
{
    $mv_AppointmentSerNum = $row["AppointmentSerNum"];

    //check if this appointment is the appointment that was moved in the VWR
    $intendedAppointment = 0;
    if($medivisitOriginal and $mv_AppointmentSerNum == $originalAppointmentSer)
    {
        $intendedAppointment = 1;
    }

    // Check in to MediVisit/MySQL appointment, if there is one

    # since a script exists for this, best to call it here rather than rewrite the wheel
    $mv_CheckinURL_raw = "$baseURL/php/checkinPatientMV.php?checkinVenue=$checkinVenue&scheduledActivitySer=$mv_AppointmentSerNum&intendedAppointment=$intendedAppointment";
    $mv_CheckinURL = str_replace(' ','%20',$mv_CheckinURL_raw);

    $lines = file_get_contents($mv_CheckinURL);

    #if the appointment originates from Aria, call the AriaIE to update the Aria db
    if($row["AppointSys"] === "Aria" && $ariaURL !== NULL)
    {
        $trueAppId = preg_replace("/Aria/","",$row["AppointId"]);
        $aria_checkin = "$ariaURL?appointmentId=$trueAppId&location=$checkinVenue";
        $aria_checkin = str_replace(' ','%20',$aria_checkin);
        file_get_contents($aria_checkin);
    }
}

echo "Patient location updated";

?>
