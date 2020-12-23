<?php
###################################################
# live version of config
###################################################

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;

//define some constants
$configs = Config::GetAllConfigs();

define("BASE_URL",$configs["path"]["BASE_URL"]."/VirtualWaitingRoom");
define("BASE_PATH",$configs["path"]["BASE_PATH"]."/VirtualWaitingRoom");

//define the location of the checked in patients text file
define("CHECKIN_FILE_PATH",BASE_PATH ."/checkin/checkinlist.txt");
define("CHECKIN_FILE_URL", BASE_URL ."/checkin/checkinlist.txt");
define("OPAL_NOTIFICATION_URL",$configs["opal"]["OPAL_NOTIFICATION_URL"]);

//Variables for firebase

define( "FIREBASE_URL",$configs["vwr"]["FIREBASE_URL"]);
define( "FIREBASE_SECRET",$configs["vwr"]["FIREBASE_SECRET"]);

//Initialize the data logging function
function LOG_MESSAGE($identifier,$type,$message)
{
    $callingScript = basename($_SERVER['SCRIPT_FILENAME']);

    chdir(BASE_PATH."/perl");
    exec("./logMessage.pl 'filename=$callingScript&identifier=$identifier&type=$type&message=$message'");
    chdir("../php");
}

?>
