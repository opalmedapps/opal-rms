<?php
###################################################
# live version of config
###################################################

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

//get the current directory
$baseLoc = dirname(__FILE__);
$baseLoc = str_replace("/php","",$baseLoc);

define("BASE_URL",str_replace("/var/www/OnlineRoomManagementSystem/","http://172.26.123.84
",$baseLoc));
define("BASE_PATH",$baseLoc);

//define the location of the checked in patients text file
define("CHECKIN_FILE_PATH",BASE_PATH ."/checkin/checkinlist.txt");
define("CHECKIN_FILE_URL", BASE_URL ."/checkin/checkinlist.txt");

//DB Settings
define("MYSQL_HOST","172.26.125.194");
define("MYSQL_PORT","3306");
define("WAITROOM_DB","WaitRoomManagement");
define("MYSQL_USERNAME","ormsadm");
define("MYSQL_PASSWORD","aklw3hrq3asdf923k");

define("QUESTIONNAIRE_DB","QuestionnairesNew");

//Opal Settings
define("OPAL_HOST","172.26.120.179");
define("OPAL_PORT","3306");
define("OPAL_DB","OpalDB");
define("OPAL_USERNAME","opalAdmin");
define("OPAL_PASSWORD","nChs2Gfs1FeubVK0");

//PDO specific variables/options
define("WRM_CONNECT","mysql:host=". MYSQL_HOST .";port=". MYSQL_PORT .";dbname=". WAITROOM_DB);
define("QUESTIONNAIRE_CONNECT","mysql:host=". MYSQL_HOST .";port=". MYSQL_PORT .";dbname=". QUESTIONNAIRE_DB);
define("OPAL_CONNECT","mysql:host=". OPAL_HOST .";port=". OPAL_PORT .";dbname=". OPAL_DB);

$WRM_OPTIONS = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_EMULATE_PREPARES => true];

$ARIA_OPTIONS = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

$OPAL_OPTIONS = [
	PDO::ATTR_EMULATE_PREPARES => true];

//Variables for SMS messages
define( "SMS_licencekey", "f0d8384c-ace2-46ef-8fa4-6e54d389ff51" );
define( "SMS_gatewayURL", "https://sms2.cdyne.com/sms.svc/SecureREST/SimpleSMSsend" );
define( "MUHC_SMS_webservice", "http://172.26.119.60/WS_Transport/Transport.asmx?WSDL");

define( "FIREBASE_URL", "https://virtualwaitingroom-8869c.firebaseio.com/RVH/");
define( "FIREBASE_SECRET", "k5zGA9zJa3SFSsrvRoExD1V45s0iEXFFUaFRwnAN");

//Initialize the data logging function
function LOG_MESSAGE($identifier,$type,$message)
{
	$callingScript = basename($_SERVER['SCRIPT_FILENAME']);

	chdir(BASE_PATH."/perl");
	exec("./logMessage.pl 'filename=$callingScript&identifier=$identifier&type=$type&message=$message'");
	chdir("../php");
}

function utf8_encode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_encode_recursive($val);
    elseif (is_string ($data)) return utf8_encode($data);

    return $data;
}

#encodes the values of an array from utf8 to latin1
#also works on array of arrays or other nested structures
function utf8_decode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_decode_recursive($val);
    elseif (is_string ($data)) return utf8_decode($data);

    return $data;
}

?>
