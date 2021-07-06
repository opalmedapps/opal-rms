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

use Orms\DateTime;
use Orms\Util\Encoding;
use Orms\Util\ArrayUtil;
use Orms\DataAccess\ReportAccess;

//parse input parameters
$sDate  = $_GET["sDate"] ?? NULL;
$eDate  = $_GET["eDate"] ?? NULL;
$period = $_GET["period"] ?? NULL;
$speciality = $_GET["speciality"] ?? NULL;

//check what period of the day the user specified and filter appointments that are not within that timeframe
$sDate = DateTime::createFromFormatN("Y-m-d",$sDate)?->modifyN("midnight") ?? throw new Exception("Invalid date");
$eDate = DateTime::createFromFormatN("Y-m-d",$eDate)?->modifyN("tomorrow") ?? throw new Exception("Invalid date");

if($period === "AM") {
    $sTime = (new DateTime())->modifyN("midnight") ?? throw new Exception("Invalid time");
    $eTime = DateTime::createFromFormatN("H:i:s","13:00:00") ?? throw new Exception("Invalid time");
}
elseif($period === "PM") {
    $sTime = DateTime::createFromFormatN("H:i:s","13:00:00") ?? throw new Exception("Invalid time");
    $eTime = DateTime::createFromFormatN("H:i:s","23:59:59") ?? throw new Exception("Invalid time");
}
else {
    $sTime = (new DateTime())->modifyN("midnight") ?? throw new Exception("Invalid time");
    $eTime = DateTime::createFromFormatN("H:i:s","23:59:59") ?? throw new Exception("Invalid time");
}

//get a list of all rooms that patients were checked into and for which appointment
$rooms = ReportAccess::getRoomUsage($sDate,$eDate,$sTime,$eTime,(int) $speciality);

$dataArr = ArrayUtil::groupArrayByKeyRecursive(Encoding::utf8_encode_recursive($rooms),"CheckinVenueName","ResourceName");

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
