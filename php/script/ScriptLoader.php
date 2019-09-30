<?php

ScriptLoader::LoadClasses();

class ScriptLoader
{
	public static function LoadClasses()
	{
		include_once(dirname(__FILE__) ."/../AutoLoader.php");
	}
}

?>