<?php

declare(strict_types = 1);
error_reporting(E_ALL);

date_default_timezone_set("America/Montreal");

include_once("GlobalFunctions.php");

AutoLoader::init();
spl_autoload_register(["AutoLoader","Autoload"]);

class AutoLoader
{
	private static $classPath;

	public static function init()
	{
        if(!isset(self::$classPath)) {self::$classPath = dirname(__FILE__) ."/class";}
	}

	public static function Autoload($className)
	{
		include_once(self::$classPath ."/$className.php");
	}
}

?>