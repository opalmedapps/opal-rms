<?php declare(strict_types=1);
//====================================================================================
// php code to check a patient into all appointments
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use GuzzleHttp\Client;

use Orms\Config;
use Orms\Database;

$conn = Database::getOrmsConnection();

$baseURL = Config::getApplicationSettings()->environment->baseUrl;
$ariaURL = Config::getApplicationSettings()->aria?->checkInUrl;

// Extract the webpage parameters
$PushNotification   = 0; # default of zero
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
    #$MV_PatientId = $row["PatientId"];
    $MV_PatientFirstName        = $row["FirstName"];
    $MV_PatientLastName         = $row["LastName"];
    $MV_ScheduledStartTime      = $row["ScheduledDateTime"];
    $MV_ApptDescription         = $row["AppointmentCode"];
    $MV_Resource                = $row["ResourceDescription"];
    $MV_AptTimeSinceMidnight    = $row["AptTimeSinceMidnight"];
    $MV_AppointmentSerNum       = $row["AppointmentSerNum"];
    $MV_Status                  = $row["Status"];

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

// Send patientID to James and Yick's OpalCheckin script to synchronize the checkin state with OpalDB and send notifications
if($PushNotification == 1)
{
    $opalCheckinURL = Config::getApplicationSettings()->opal?->checkInUrl;

    if($opalCheckinURL !== NULL)
    {
        try {
            $response = $client->request("GET",$opalCheckinURL,[
                "query" => [
                    "PatientId" => $PatientId
                ]
            ])->getBody()->getContents();
        }
        catch(Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(),E_USER_WARNING);
        }
    }

    $response = $response ?? "";

    if(strpos($response, 'Error')){
    $response = ['error' => $response];
    } else {
    $response = ['success' => explode(',',  trim($response))];
    }

    $response = json_encode($response);

    print($response);
} # End of push notification

?>
