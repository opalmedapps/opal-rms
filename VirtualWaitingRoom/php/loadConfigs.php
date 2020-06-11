<?php
###################################################
# live version of config
###################################################

require __DIR__."/../../php/AutoLoader.php";

//set all error reporting on for security purposes
error_reporting(E_ALL);

date_default_timezone_set("America/Montreal");

//except Undefined index errors since every $_GET[] statement throws it to the screen
//unless someone wants to rewrite all $_GET[] statements in all the scripts
function handleNoticeError($errno,$errstr,$errfile,$errline)
{
	if(strpos($errstr,'Notice: Undefined index') !== false)
	{
		return false; //don't call the php standard error handler
	}
	else {return true;} //call the standard error handler
}
set_error_handler('handleNoticeError', E_NOTICE);

//define some constants
$configs = Config::GetAllConfigs();

define("BASE_URL",$configs["path"]["BASE_URL"]."/VirtualWaitingRoom");
define("BASE_PATH",$configs["path"]["BASE_PATH"]."/VirtualWaitingRoom");

//define the location of the checked in patients text file
define("CHECKIN_FILE_PATH",BASE_PATH ."/checkin/checkinlist.txt");
define("CHECKIN_FILE_URL", BASE_URL ."/checkin/checkinlist.txt");

//DB Settings
define("MYSQL_HOST",$configs["database"]["ORMS_HOST"]);
define("MYSQL_PORT",$configs["database"]["ORMS_PORT"]);
define("WAITROOM_DB",$configs["database"]["ORMS_DB"]);
define("MYSQL_USERNAME",$configs["database"]["ORMS_USERNAME"]);
define("MYSQL_PASSWORD",$configs["database"]["ORMS_PASSWORD"]);

//Opal Settings
define("OPAL_HOST",$configs["database"]["OPAL_HOST"]);
define("OPAL_PORT",$configs["database"]["OPAL_PORT"]);
define("OPAL_DB",$configs["database"]["OPAL_DB"]);
define("OPAL_USERNAME",$configs["database"]["OPAL_USERNAME"]);
define("OPAL_PASSWORD",$configs["database"]["OPAL_PASSWORD"]);

//PDO specific variables/options
define("WRM_CONNECT","mysql:host=". MYSQL_HOST .";port=". MYSQL_PORT .";dbname=". WAITROOM_DB);
define("OPAL_CONNECT","mysql:host=". OPAL_HOST .";port=". OPAL_PORT .";dbname=". OPAL_DB);

$WRM_OPTIONS = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_EMULATE_PREPARES => true];

$ARIA_OPTIONS = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

$OPAL_OPTIONS = [
	PDO::ATTR_EMULATE_PREPARES => true];

//Variables for SMS messages
define( "SMS_licencekey",$config["sms"]["SMS_LICENCE_KEY"]);
define( "SMS_gatewayURL", $configs["sms"]["SMS_GATEWAY_URL"]);
define( "MUHC_SMS_webservice",$configs["sms"]["SMS_WEBSERVICE_MUHC"]);

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
