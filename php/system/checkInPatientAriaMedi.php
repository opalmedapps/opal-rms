<?php
//====================================================================================
// php code to check a patient into all appointments
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$conn = Config::getDatabaseConnection("ORMS");

$baseURL = Config::getConfigs("path")["BASE_URL"];
$ariaURL = Config::getConfigs("aria")["ARIA_CHECKIN_URL"] ?? NULL;

// Extract the webpage parameters
$PushNotification   = 0; # default of zero
$CheckinVenue       = !empty($_GET["CheckinVenue"]) ? $_GET["CheckinVenue"] : NULL;
$PatientId          = !empty($_GET["PatientId"]) ? $_GET["PatientId"] : NULL;
$PushNotification   = !empty($_GET["PushNotification"]) ? $_GET["PushNotification"] : NULL;
$verbose            = !empty($_GET["verbose"]) ? $_GET["verbose"] : NULL;

if ($verbose) echo "Verbose Mode<br>";

if (!$conn) {
    die("<br>Connection failed");
}

//======================================================================================
// Check for upcoming appointments for this patient
//======================================================================================
$Today = date("Y-m-d");
$startOfToday = "$Today 00:00:00";
$endOfToday = "$Today 23:59:59";

if ($verbose) echo "startOfToday: $startOfToday, endOfToday: $endOfToday<p>";

############################################################################################
######################################### Medivisit ########################################
############################################################################################
$sqlApptMedivisit = "
    SELECT DISTINCT
        Patient.PatientId,
        Patient.FirstName,
        Patient.LastName,
        MediVisitAppointmentList.ScheduledDateTime,
        MediVisitAppointmentList.AppointmentCode,
        MediVisitAppointmentList.ResourceDescription,
        (UNIX_TIMESTAMP(MediVisitAppointmentList.ScheduledDateTime)-UNIX_TIMESTAMP('$startOfToday'))/60 AS AptTimeSinceMidnight,
        MediVisitAppointmentList.AppointmentSerNum,
        MediVisitAppointmentList.Status,
        MediVisitAppointmentList.AppointId,
        MediVisitAppointmentList.AppointSys
    FROM
        Patient,
        MediVisitAppointmentList
        WHERE
        MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
        AND MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
        AND Patient.PatientId = '$PatientId'
                AND ( MediVisitAppointmentList.ScheduledDateTime >= '$startOfToday' )
                AND ( MediVisitAppointmentList.ScheduledDateTime < '$endOfToday' )
        AND MediVisitAppointmentList.Status = 'Open'
    ORDER BY MediVisitAppointmentList.ScheduledDateTime
";

if ($verbose) echo "sqlApptMedivisit:<br> $sqlApptMedivisit<p>";

/* Process results */
$result = $conn->query($sqlApptMedivisit);

// output data of each row
while($row = $result->fetch())
{
    #$MV_PatientId = $row["PatientId"];
    $MV_PatientFirstName        = $row["FirstName"];
    $MV_PatientLastName         = $row["LastName"];
    $MV_ScheduledStartTime      = $row["ScheduledDateTime"];
    $MV_ApptDescription         = $row["AppointmentCode"];
    $MV_Resource                = $row["ResourceDescription"];
    $MV_AptTimeSinceMidnight    = $row["AptTimeSinceMidnight"];
    $MV_AppointmentSerNum       = $row["AppointmentSerNum"];
    $MV_Status                  = $row["Status"];

    if ($verbose) echo "<br> MV appt: $MV_ApptDescription at $MV_ScheduledStartTime with $MV_Resource<br>";

    // Check in to MediVisit/MySQL appointment, if there is one
    if ($verbose) echo "About to attempt Medivisit checkin<br>";

    # since a script exists for this, best to call it here rather than rewrite the wheel
    $MV_CheckInURL_raw = "$baseURL/php/system/checkInPatientMV.php?CheckinVenue=$CheckinVenue&ScheduledActivitySer=$MV_AppointmentSerNum";
    $MV_CheckInURL = str_replace(' ', '%20', $MV_CheckInURL_raw);

    if ($verbose) echo "MV_CheckInURL: $MV_CheckInURL<br>";

    $lines = file_get_contents($MV_CheckInURL);

    #if the appointment originates from Aria, call the AriaIE to update the Aria db
    if($row["AppointSys"] === "Aria" && $ariaURL !== NULL)
    {
        $trueAppId = preg_replace("/Aria/","",$row["AppointId"]);
        $aria_checkin = "$ariaURL?appointmentId=$trueAppId&location=$CheckinVenue";
        $aria_checkin = str_replace(' ','%20',$aria_checkin);
        file_get_contents($aria_checkin);
    }
}

// Send patientID to James and Yick's OpalCheckin script to synchronize the checkin state with OpalDB and send notifications
if($PushNotification == 1)
{
    $opalCheckinURL = Config::getConfigs("opal")["OPAL_CHECKIN_URL"];

    $opalCheckinURL = "$opalCheckinURL?PatientId=$PatientId";
    $opalCheckinURL = str_replace(' ', '%20', $opalCheckinURL);

    $response = file_get_contents($opalCheckinURL) ?: "";

    if(strpos($response, 'Error')){
    $response = ['error' => $response];
    } else {
    $response = ['success' => explode(',',  trim($response))];
    }

    $response = json_encode($response);

    print($response);
} # End of push notification
?>
