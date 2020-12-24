<?php
//gets the location of the text files that contains the list of checked in patients and returns it to a webpage

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$checkinFile = Config::getConfigs("path")["BASE_URL"] ."/VirtualWaitingRoom/checkin/checkinlist.txt";
$opalNotificationUrl = Config::getConfigs("opal")["OPAL_NOTIFICATION_URL"];

echo json_encode([
    "checkinFile" => $checkinFile,
    "opalNotificationUrl" => $opalNotificationUrl
]);

?>
