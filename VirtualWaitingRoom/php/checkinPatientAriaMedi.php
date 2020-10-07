<?php
//====================================================================================
// checkinPatientAriaMedi.php - php code to check a patient into all appointments
//====================================================================================
require("loadConfigs.php");

use Orms\Config;

$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Extract the webpage parameters
$checkinVenue = $_GET["checkinVenue"];
$originalAppointmentSer = $_GET["appointmentSer"];
$patientIdRVH = $_GET["patientIdRVH"];
$patientIdMGH = $_GET["patientIdMGH"];

$waitroom = WAITROOM_DB;
$baseURL = BASE_URL;

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
$sqlApptMedivisit = "
    SELECT DISTINCT
        Patient.PatientId,
        Patient.PatientId_MGH,
        Patient.FirstName,
        Patient.LastName,
        MediVisitAppointmentList.ScheduledDateTime,
        MediVisitAppointmentList.AppointmentCode,
        MediVisitAppointmentList.ResourceDescription,
        (UNIX_TIMESTAMP(MediVisitAppointmentList.ScheduledDateTime)-UNIX_TIMESTAMP('$startOfToday'))/60 AS AptTimeSinceMidnight,
        MediVisitAppointmentList.AppointmentSerNum,
        MediVisitAppointmentList.Status,
        MediVisitAppointmentList.AppointSys,
        MediVisitAppointmentList.AppointId
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
            AND MediVisitAppointmentList.ScheduledDateTime >= '$startOfToday'
            AND MediVisitAppointmentList.ScheduledDateTime < '$endOfToday'
            AND MediVisitAppointmentList.Status = 'Open'
    WHERE
        Patient.PatientId = '$patientIdRVH'
        AND Patient.PatientId_MGH = '$patientIdMGH'
    ORDER BY MediVisitAppointmentList.ScheduledDateTime";

/* Process results */
$result = $dbWRM->query($sqlApptMedivisit);

//check if the appointment that was moved in the VWR is a Medivisit appointment
//if it is, then we should indicate that the patient was checked into the room because of this appointment in the PatientLocation table
$medivisitOriginal = 0;
if(strstr($originalAppointmentSer,'Medivisit')) //check if the appointment is a Medivist one in the first place
{
    $medivisitOriginal = 1;
    $originalAppointmentSer = str_replace('Medivisit','',$originalAppointmentSer);
}


// output data of each row
while($row = $result->fetch(PDO::FETCH_ASSOC))
{
    $mv_PatientFirstName = $row["FirstName"];
    $mv_PatientLastName = $row["LastName"];
    $mv_ScheduledStartTime = $row["ScheduledStartTime"];
    $mv_ApptDescription = $row["AppointmentCode"];
    $mv_Resource = $row["ResourceDescription"];
    $mv_AptTimeSinceMidnight = $row["AptTimeSinceMidnight"];
    $mv_AppointmentSerNum = $row["AppointmentSerNum"];
    $mv_Status = $row["Status"];

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

//close connections
$dbWRM = null;

?>
