<?php

Config::init();

class Config
{

	private static $configs;

	#class constructor
	public static function init()
	{
		#load the config file
		self::$configs = parse_ini_file(dirname(__FILE__) ."/../../config/configDev.conf",true);
	}

	#returns a hash with all configs
	public static function GetAllConfigs()
	{
		return self::$configs;
	}

	#returns a hash with specific configs
	public static function getConfigs($section)
	{
		return self::$configs[$section];
	}

	#returns a db connection handle to a requested database server
	#options are currently predefined as "ORMS" or "ARIA"
	#return 0 if connection fails
	public static function getDatabaseConnection($requestedConnection)
	{
		$dbInfo = self::$configs['database'];
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		];

		#set the inital value of the connection to 0 (failure value)
		#the requesting script can then determine what to do if the db fails to connect
		$dbh = NULL;

		#connects to WaitRoomManagment db by default
		if($requestedConnection == 'ORMS')
		{
			$dbh = new PDO("mysql:host={$dbInfo['ORMS_HOST']};port={$dbInfo['ORMS_PORT']};dbname={$dbInfo['ORMS_DB']}",$dbInfo['ORMS_USERNAME'],$dbInfo['ORMS_PASSWORD'],$options);
		}

		#connects to variansystem by default
		elseif($requestedConnection == 'ARIA')
		{
			$dbh = new PDO("odbc:DRIVER=FreeTDS;SERVER={$dbInfo['ARIA_HOST']};PORT={$dbInfo['ARIA_PORT']};DATABASE={$dbInfo['ARIA_DB']}",$dbInfo['ARIA_USERNAME'],$dbInfo['ARIA_PASSWORD'],$options);
		}

		#logging db
		elseif($requestedConnection == 'LOGS')
		{
			$dbh = new PDO("mysql:host={$dbInfo['LOG_HOST']};port={$dbInfo['LOG_PORT']};dbname={$dbInfo['LOG_DB']}",$dbInfo['LOG_USERNAME'],$dbInfo['LOG_PASSWORD'],$options);
		}

		#opal db
		elseif($requestedConnection == 'OPAL')
		{
			$dbh = new PDO("mysql:host={$dbInfo['OPAL_HOST']};port={$dbInfo['OPAL_PORT']};dbname={$dbInfo['OPAL_DB']}",$dbInfo['OPAL_USERNAME'],$dbInfo['OPAL_PASSWORD'],$options);
		}

		return $dbh;
	}
}

?>