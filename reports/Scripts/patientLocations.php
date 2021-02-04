<?php declare(strict_types = 1);

#---------------------------------------------------------------------------------------------------------------
# This script finds all the locations patients visited during the specified time range for the appointments in the specified time range and in the ORMS database.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Database;

#parse input parameters
$sDate                  = $_GET["sDate"] ?? NULL; $sDate .= " 00:00:00";
$eDate                  = $_GET["eDate"] ?? NULL; $eDate .= " 23:59:59";
$clinic                 = $_GET["clinic"] ?? NULL;
$specifiedAppointment   = $_GET["filter"] ?? NULL;

$specialityFilter = "";
if($clinic === 'onc') $specialityFilter = " AND CR.Speciality = 'Oncology' ";
elseif($clinic === 'ortho') $specialityFilter = " AND CR.Speciality = 'Ortho' ";

$appointmentFilter = ($specifiedAppointment) ?  " AND AC.AppointmentCode LIKE '%$specifiedAppointment%' " : "";

#connect to the database and extract data
$dbh = Database::getOrmsConnection();

#get a list of check in/outs for patients who had an appointment in the specified date range
#this includes the PatientLocation table
$query = $dbh->prepare("
    SELECT DISTINCT
        UN.ScheduledDate,
        UN.AppointmentSerNum,
        UN.PatientSerNum,
        UN.FirstName,
        UN.LastName,
        UN.PatientId,
        UN.CheckinVenueName,
        UN.ArrivalDateTime AS Arrival,
        UN.DichargeThisLocationDateTime AS Discharge,
        UN.ResourceName,
        UN.AppointmentCode,
        UN.Status,
        UN.ScheduledTime,
        UN.PatientLocationRevCount
    FROM (
        SELECT DISTINCT
            MV.ScheduledDate,
            MV.AppointmentSerNum,
            Patient.PatientSerNum,
            Patient.FirstName,
            Patient.LastName,
            Patient.PatientId,
            PatientLocationMH.CheckinVenueName,
            PatientLocationMH.ArrivalDateTime,
            PatientLocationMH.DichargeThisLocationDateTime,
            PatientLocationMH.PatientLocationRevCount,
            CR.ResourceName,
            AC.AppointmentCode,
            MV.Status,
            MV.ScheduledTime
        FROM
            Patient
            INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = Patient.PatientSerNum
                AND MV.Status != 'Deleted'
                AND MV.ScheduledDate BETWEEN :sDate AND :eDate
            LEFT JOIN PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MV.AppointmentSerNum
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                $specialityFilter
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                $appointmentFilter
        UNION
        SELECT DISTINCT
            MV.ScheduledDate,
            MV.AppointmentSerNum,
            Patient.PatientSerNum,
            Patient.FirstName,
            Patient.LastName,
            Patient.PatientId,
            PatientLocation.CheckinVenueName,
            PatientLocation.ArrivalDateTime,
            '1970' AS DichargeThisLocationDateTime,
            PatientLocation.PatientLocationRevCount,
            CR.ResourceName,
            AC.AppointmentCode,
            MV.Status,
            MV.ScheduledTime
        FROM
            Patient
            INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = Patient.PatientSerNum
                AND MV.Status != 'Deleted'
                AND MV.ScheduledDate BETWEEN :sDate AND :eDate
            LEFT JOIN PatientLocation ON PatientLocation.AppointmentSerNum = MV.AppointmentSerNum
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                $specialityFilter
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                $appointmentFilter
        ) AS UN
    ORDER BY
        UN.ScheduledDate,
        UN.PatientId,
        UN.AppointmentSerNum,
        UN.PatientLocationRevCount
");
$query->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate
]);

$checkIns = array_map(function($checkIn) {
    #if the appointment has at least one PatientLocation/PatientLocationMH row associated with it, then we can determine the check in time and other information
    #if not, we just mark the relevant arrays as empty
    $dataArr = [];
    $chartDataArr = [];

    if($checkIn["PatientLocationRevCount"])
    {
        #1970 was assigned in the query if there was no checkout
        if($checkIn["Discharge"] === "1970") $checkIn["Discharge"] = "Not Checked Out";

        #hours determines the width the venue will take in the highcharts chart
        $color = assignColor($checkIn["CheckinVenueName"] ?? "");
        $start = (new DateTime($checkIn["Arrival"]))->getTimestamp();

        if($checkIn["Discharge"] === "Not Checked Out")
        {
            $end = (new DateTime())->getTimestamp();
            $checkIn["CheckinVenueName"] = "Current: $checkIn[CheckinVenueName]";
        }
        else
        {
            $end = (new DateTime($checkIn["Discharge"]))->getTimestamp();
        }

        $waitTime = ($end - $start)/3600;

        #if the value is too small, it won't be visible on the webpage
        if($waitTime < 0.02) $waitTime = 0.02;
        $waitTime = (float) number_format($waitTime,2);

        $dataArr[] = [
            $checkIn["CheckinVenueName"],
            $checkIn["Arrival"],
            $checkIn["Discharge"],
            ($waitTime === 0.02) ? "0.0" : "$waitTime"
        ];

        $chartDataArr[] = [
            $waitTime,
            $color,
            $checkIn["CheckinVenueName"]
        ];
    }

    return [
        "date"      => $checkIn["ScheduledDate"],
        "ser"       => $checkIn["AppointmentSerNum"],
        "fname"     => $checkIn["FirstName"],
        "lname"     => $checkIn["LastName"],
        "pID"       => $checkIn["PatientId"],
        "app"       => $checkIn["ResourceName"],
        "code"      => $checkIn["AppointmentCode"],
        "status"    => $checkIn["Status"],
        "time"      => $checkIn["ScheduledTime"],
        "data"      => $dataArr,
        "chartData" => $chartDataArr,
    ];
},$query->fetchAll());

#group the check ins by appointment serial number so that we can combine all check ins belonging to the same appointment
$appointments = [];
foreach($checkIns as $checkIn) {
    $appointments[$checkIn["ser"]][] = $checkIn;
}

$appointments = array_values(array_map(function($appointment) {
    $appointment = array_merge_recursive(...$appointment);

    $appointment["date"]    = $appointment["date"][0] ?? "";
    $appointment["ser"]     = $appointment["ser"][0];
    $appointment["fname"]   = $appointment["fname"][0];
    $appointment["lname"]   = $appointment["lname"][0];
    $appointment["pID"]     = $appointment["pID"][0];
    $appointment["app"]     = $appointment["app"][0];
    $appointment["code"]    = $appointment["code"][0];
    $appointment["status"]  = $appointment["status"][0];
    $appointment["time"]    = $appointment["time"][0];

    #create initial entry for chartData from midnight to first check in
    #only add it if the patient is already checked in
    if($appointment["chartData"] !== [])
    {
        $start = (new DateTime($appointment["date"] ?? ""))->getTimestamp();
        $end = (new DateTime($appointment["data"][0][1] ?? ""))->getTimestamp();

        $waitTime = ($end - $start)/3600;

        #if the valueis too small, it won't be visible on the webpage
        if($waitTime < 0.02) $waitTime = 0.02;
        $waitTime = (float) number_format($waitTime,2);

        $chartDataArr = [
            $waitTime,
            "transparent",
            "Not Checked In"
        ];

        array_unshift($appointment["chartData"],$chartDataArr);
    }

    return $appointment;
},$appointments));

usort($appointments,function($appA,$appB){
    return $appA["pID"] <=> $appB["pID"];
});

#group the appointments by date and order them by patient Id
$dates = [];
foreach($appointments as $appointment)
{
    $dates[$appointment["date"]][] = $appointment;
}

$dates = utf8_encode_recursive($dates);
echo json_encode($dates);

#functions
function assignColor(string $room): string
{
    $color = "#e6e6e6";

    if(preg_match("/EXAM ROOM/",$room))                             $color = '#ff0000';
    elseif(preg_match("/Cast Room/",$room))                         $color = '#0099ff';
    elseif(preg_match("/Ortho Waiting Room/",$room))                $color = '#99d6ff';
    elseif(preg_match("/Ortho Room/",$room))                        $color = '#0066ff';
    elseif(preg_match("/TX AREA/",$room))                           $color = '#ffff00';
    elseif(preg_match("/(RC Waiting Room|RC WAITING ROOM)/",$room)) $color = '#66ff33';
    elseif(preg_match("/S1 Waiting Room/",$room))                   $color = '#cc99ff';
    elseif(preg_match("/SENT FOR X-RAY/",$room))                    $color = '#996633';
    elseif(preg_match("/SENT FOR PHYSIO/",$room))                   $color = '#ff9933';
    elseif(preg_match("/TEST CENTRE WAITING ROOM/",$room))          $color = '#cc00ff';
    elseif(preg_match("/VISIT COMPLETE/",$room))                    $color = '#000001';
    elseif(preg_match("/BACK FROM X-RAY\/PHYSIO/",$room))           $color = '#ff33cc';
    elseif(preg_match("/Ortho Treatment Room/",$room))              $color = '#000099';

    return $color;
}

?>
