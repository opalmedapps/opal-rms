<?php declare(strict_types=1);
//====================================================================================
// php code to check a patient into all appointments
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use GuzzleHttp\Client;

use Orms\Config;
use Orms\Database;
use Orms\Opal;

$conn = Database::getOrmsConnection();

$baseURL = Config::getApplicationSettings()->environment->baseUrl;
$ariaURL = Config::getApplicationSettings()->aria?->checkInUrl;

// Extract the webpage parameters
$CheckinVenue       = !empty($_GET["CheckinVenue"]) ? $_GET["CheckinVenue"] : NULL;
$PatientId          = !empty($_GET["PatientId"]) ? $_GET["PatientId"] : NULL;
$PushNotification   = !empty($_GET["PushNotification"]) ? $_GET["PushNotification"] : NULL;

//======================================================================================
// Check for upcoming appointments for this patient
//======================================================================================
$Today = date("Y-m-d");
$startOfToday = "$Today 00:00:00";
$endOfToday = "$Today 23:59:59";

$client = new Client;

############################################################################################
######################################### Medivisit ########################################
############################################################################################
$queryApptMedivisit = $conn->prepare("
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
");
$queryApptMedivisit->execute();

// output data of each row
foreach($queryApptMedivisit->fetchAll() as $row)
{
    $MV_AppointmentSerNum       = $row["AppointmentSerNum"];

    # since a script exists for this, best to call it here rather than rewrite the wheel
    try {
        $client->request("GET","$baseURL/php/system/checkInPatientMV.php",[
            "query" => [
                "CheckinVenue" => $CheckinVenue,
                "ScheduledActivitySer" => $MV_AppointmentSerNum
            ]
        ]);
    }
    catch(Exception $e) {
        trigger_error($e->getMessage() ."\n". $e->getTraceAsString(),E_USER_WARNING);
    }

    #if the appointment originates from Aria, call the AriaIE to update the Aria db
    if($row["AppointSys"] === "Aria" && $ariaURL !== NULL)
    {
        $trueAppId = preg_replace("/Aria/","",$row["AppointId"]);

        try {
            $client->request("GET",$ariaURL,[
                "query" => [
                    "appointmentId" => $trueAppId,
                    "location" => $CheckinVenue
                ]
            ]);
        }
        catch(Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(),E_USER_WARNING);
        }
    }
}

//send a push notification to opal for the patient
if($PushNotification == 1) Opal::sendCheckInNotification($PatientId);

?>
