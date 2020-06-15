<?php

require __DIR__."/../../php/AutoLoader.php";

//set off all error for security purposes
error_reporting(E_ALL);

$configs = Config::GetAllConfigs();

//define some contstants
define( "DB_HOST",$configs["database"]["OPAL_HOST"]);
define( "DB_PORT",$configs["database"]["OPAL_PORT"]);
define( "DB_USERNAME",$configs["database"]["OPAL_USERNAME"]);
define( "DB_PASSWORD",$configs["database"]["OPAL_PASSWORD"]);
define( "DB_NAME",$configs["database"]["QUESTIONNAIRE_DB"]);

define( "OPAL_DB",$configs["database"]["OPAL_DB"]);

?>
