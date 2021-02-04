<?php declare(strict_types = 1);

// legacy report script refactored from perl

#---------------------------------------------------------------------------------------------------------------
# This script finds all appointment in the specified time range and outputs all patient checkins and venue movements
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Database;

#------------------------------------------
#parse input parameters
#------------------------------------------
$sDateInit = $_GET["sDate"];
$eDateInit = $_GET["eDate"];
$clinic    = $_GET["clinic"];

$sDate = $sDateInit ." 00:00:00";
$eDate = $eDateInit ." 23:59:59";

$specialityFilter = "";
if($clinic === "onc")   $specialityFilter = "AND ClinicResources.Speciality = 'Oncology' ";
if($clinic === "ortho") $specialityFilter = "AND ClinicResources.Speciality = 'Ortho' ";

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
$dbh = Database::getOrmsConnection();

#get a list of check in/outs for patients who had an appointment in the specified date range
#this includes the PatientLocation table
$query = $dbh->prepare("
    SELECT
        MediVisitAppointmentList.ScheduledDate,
        MediVisitAppointmentList.AppointmentSerNum,
        Patient.PatientSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        PatientLocations.CheckinVenueName,
        PatientLocations.ArrivalDateTime,
        PatientLocations.DichargeThisLocationDateTime,
        PatientLocations.PatientLocationRevCount,
        MediVisitAppointmentList.ResourceDescription,
        MediVisitAppointmentList.AppointmentCode,
        MediVisitAppointmentList.Status,
        MediVisitAppointmentList.ScheduledTime
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
            AND MediVisitAppointmentList.Status != 'Deleted'
            AND MediVisitAppointmentList.Status != 'Cancelled'
            AND MediVisitAppointmentList.ScheduledDate BETWEEN :sDate AND :eDate
        INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
            $specialityFilter
        LEFT JOIN (
            SELECT
                PatientLocationMH.CheckinVenueName,
                PatientLocationMH.ArrivalDateTime,
                PatientLocationMH.DichargeThisLocationDateTime,
                PatientLocationMH.PatientLocationRevCount,
                PatientLocationMH.AppointmentSerNum
            FROM
                PatientLocationMH
            UNION
            SELECT
                PatientLocation.CheckinVenueName,
                PatientLocation.ArrivalDateTime,
                'NOT CHECKED OUT' AS DichargeThisLocationDateTime,
                PatientLocation.PatientLocationRevCount,
                PatientLocation.AppointmentSerNum
            FROM
                PatientLocation
        ) AS PatientLocations ON PatientLocations.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
    WHERE
        Patient.PatientId != '9999996'
");
$query->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate
]);

$rows = array_map(function($x) {

    #sometimes the venue is null (a bug) so in that case rename the room
    $venue = $x["CheckinVenueName"] ?? NULL;
    if($venue === NULL && $x["CheckinVenueName"] !== NULL) $venue = "The Blank Room";

    $waitTime = NULL;
    if($x["CheckinVenueName"] !== NULL && $x["DichargeThisLocationDateTime"] !== "NOT CHECKED OUT")
    {
        $checkIn = (new DateTime($x["ArrivalDateTime"]))->getTimestamp();
        $checkOut = (new DateTime($x["DichargeThisLocationDateTime"]))->getTimestamp();

        $waitTime = sprintf("%0.2f",($checkOut - $checkIn)/3600);
    }

    return [
        "PatientId"         => $x["PatientId"],
        "FirstName"         => $x["FirstName"],
        "LastName"          => $x["LastName"],
        "ScheduledDate"     => $x["ScheduledDate"],
        "ScheduledTime"     => $x["ScheduledTime"],
        "AppointmentCode"   => $x["AppointmentCode"],
        "Status"            => $x["Status"],
        "Resource"          => $x["ResourceDescription"],
        "Venue"             => $venue,
        "Arrival"           => $x["ArrivalDateTime"],
        "Discharge"         => $x["DichargeThisLocationDateTime"],
        "WaitTime"          => $waitTime,
    ];
},$query->fetchAll());

echo json_encode($rows);

?>
