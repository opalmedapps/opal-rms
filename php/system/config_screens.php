<?php
    	//set off all error for security purposes
	error_reporting(E_ALL);
	
	//define some contstants

	//get the current directory
	$baseLoc = dirname(__FILE__);
	$baseLoc = str_replace("/php","",$baseLoc);

	define("BASE_URL",str_replace("/var/www/","http://172.26.66.41/",$baseLoc));

	// Dev and Prod here as appropriate
	define( "WAITROOM_DB", "WaitRoomManagementDev" );
	//define( "WAITROOM_DB", "WaitRoomManagement" );
	//define( "WORK_ENV", "Dev"); # Dev or Prod
	define( "WORK_ENV", "Prod"); # Dev or Prod

	// Other constants that are the same between Dev and Prod
	define( "DB_HOST", "172.26.66.41" );
    	define( "DB_USERNAME", "readonly" );
	define( "DB_PASSWORD", "readonly" );
	define( "HOST", "172.26.66.41" );
	define( "PORT", "22" );
	define( "HOST_USERNAME", "webdb" );
	define( "HOST_PASSWORD", "service" );
	define( "ARIA_DB", "172.26.120.124:1433\\database" );
	define( "ARIA_USERNAME", "reports" );
	define( "ARIA_PASSWORD", "reports" );

	// Variables for SMS messages
	define( "SMS_licencekey", "f0d8384c-ace2-46ef-8fa4-6e54d389ff51" );
	define( "SMS_gatewayURL", "https://sms2.cdyne.com/sms.svc/SecureREST/SimpleSMSsend" );
	define( "MUHC_SMS_webservice", "http://172.26.119.60/WS_Transport/Transport.asmx?WSDL");

	//Pre-Prod Configuration
	define( "OPAL_DB", "OpalDB_PREPROD" );
	define( "OPAL_CHECKIN_URL", "http://172.26.66.41/Documents/opalAdmin/publisher/php/OpalCheckIn.php" );

	define( "OPAL_DB_PRODUCTION", "OpalDB" );
	define( "OPAL_CHECKIN_URL_PRODUCTION", "http://172.26.120.179/opalAdmin/publisher/php/OpalCheckIn.php" );
	
?>
