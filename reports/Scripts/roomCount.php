<?php declare(strict_types = 1);
#---------------------------------------------------------------------------------------------------------------
# This script finds all the locations patients visited during the specified time range for the appointments in the specified time range and in the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

# bugs:
#  if a patient has 2 appointments and visits the same room in the morning and the afternoon, once for each appointment, the AM + PM counts > total counts for the day
# example:
#                       |13:00|
#   |room A (for app1)  |#################|room A
#   |room A             |#################|room A (for app2)
#
#   AM counts for app1: 1
#   PM counts for app2: 1
#   true counts for the day for app1: 1 < AM + PM = 2
#   true counts for the day for app2: 1 < AM + PM = 2
#

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Util\ArrayUtil;
use Orms\Database;

#parse input parameters
$sDate  = $_GET["sDate"] ?? NULL; $sDate .= " 00:00:00";
$eDate  = $_GET["eDate"] ?? NULL; $eDate .= " 23:59:59";
$period = $_GET["period"] ?? NULL;

#check what period of the day the user specified and filter appointments that are not within that timeframe
$sTime = "00:00:00";
$eTime = "23:59:59";

if($period === "AM") $eTime = "12:59:59";
elseif($period === "PM") $sTime = "13:00:00";

#connect to the database and extract data
$dbh = Database::getOrmsConnection();

#get a list of all rooms that patients were checked into and for which appointment
$query = $dbh->prepare("
    SELECT DISTINCT
        CR.ResourceName,
        PatientLocationMH.CheckinVenueName,
        MV.ScheduledDate
    FROM
        MediVisitAppointmentList MV
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
        INNER JOIN PatientLocationMH PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MV.AppointmentSerNum
            AND PatientLocationMH.CheckinVenueName NOT IN ('VISIT COMPLETE','ADDED ON BY RECEPTION','BACK FROM X-RAY/PHYSIO','SENT FOR X-RAY','SENT FOR PHYSIO','RC RECEPTION','OPAL PHONE APP')
            AND PatientLocationMH.CheckinVenueName NOT LIKE '%WAITING ROOM%'
            AND CAST(PatientLocationMH.ArrivalDateTime AS TIME) BETWEEN :sTime AND :eTime
    WHERE
        MV.ScheduledDateTime BETWEEN :sDate AND :eDate
        AND MV.Status = 'Completed'"
);
$query->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate,
    ":sTime" => $sTime,
    ":eTime" => $eTime,
]);

$dataArr = ArrayUtil::groupArrayByKeyRecursive(Encoding::utf8_encode_recursive($query->fetchAll()),"CheckinVenueName","ResourceName");

$flattenedArr = [];
foreach($dataArr as $roomKey => $room) {
    foreach($room as $resourceKey => $resource) {
        $counts = sizeof($resource);
        $flattenedArr[] = [
            "Room" => $roomKey,
            "Appointment" => $resourceKey,
            "Counts" => $counts
        ];
    }
}

echo json_encode($flattenedArr);
