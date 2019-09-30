<?php

SystemLoader::LoadClasses();

class SystemLoader
{
	public static function LoadClasses()
	{
		include_once(dirname(__FILE__) ."/../AutoLoader.php");
	}
}

?>