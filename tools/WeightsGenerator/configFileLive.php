<?php
###################################################
# live version of config
###################################################

//set all error reporting on for security purposes
error_reporting(E_ALL);

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

//get the current directory
$baseLoc = dirname(__FILE__);
$baseLoc = str_replace("/php","",$baseLoc);

//define the location of the checked in patients text file
define("CHECKIN_FILE","$baseLoc/checkin/checkinlist.txt");

define("BASE_URL",str_replace("/var/www/","http://172.26.66.41/",$baseLoc));
define("BASE_PATH",$baseLoc);

//DB Settings
define("MYSQL_HOST","172.26.66.41");
define("MYSQL_PORT","22");
define("WAITROOM_DB","WaitRoomManagement");
define("MYSQL_USERNAME","readonly");
define("MYSQL_PASSWORD","readonly");

define("QUESTIONNAIRE_DB","QuestionnairesNew");

define("ARIA_DB","172.26.120.124:1433\\database");
define("ARIA_USERNAME","reports");
define("ARIA_PASSWORD","reports");

//Opal Settings
define("OPAL_HOST","172.26.120.179");
define("OPAL_PORT","3306");
define("OPAL_DB","OpalDB");
define("OPAL_USERNAME","opalAdmin");
define("OPAL_PASSWORD","nChs2Gfs1FeubVK0");

//PDO specific variables/options
define("WRM_CONNECT","mysql:localhost=". MYSQL_HOST .";port=". MYSQL_PORT .";dbname=". WAITROOM_DB .";charset=utf8");
define("QUESTIONNAIRE_CONNECT","mysql:localhost=". MYSQL_HOST .";port=". MYSQL_PORT .";dbname=". QUESTIONNAIRE_DB .";charset=utf8");	
define("ARIA_CONNECT","dblib:host=". ARIA_DB .";charset=utf8");
define("OPAL_CONNECT","mysql:host=". OPAL_HOST .";port=". OPAL_PORT .";dbname=". OPAL_DB .";charset=utf8");

$WRM_OPTIONS = array(
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_EMULATE_PREPARES => true);

$ARIA_OPTIONS = array(
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

$OPAL_OPTIONS = array(
	PDO::ATTR_EMULATE_PREPARES => true);

//Variables for SMS messages
define( "SMS_licencekey", "f0d8384c-ace2-46ef-8fa4-6e54d389ff51" );
define( "SMS_gatewayURL", "https://sms2.cdyne.com/sms.svc/SecureREST/SimpleSMSsend" );
define( "MUHC_SMS_webservice", "http://172.26.119.60/WS_Transport/Transport.asmx?WSDL");

//Initialize the data logging function
function LOG_MESSAGE($identifier,$type,$message)
{
	$callingScript = basename($_SERVER['SCRIPT_FILENAME']);

	chdir(BASE_PATH."/perl");
	exec("perl logMessage.pl 'filename=$callingScript&identifier=$identifier&type=$type&message=$message'");
	chdir("../php");
}

?>
