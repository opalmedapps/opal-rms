<?php
/**************************************************
    Database configuration
**************************************************/
require __DIR__."/../../../php/AutoLoader.php";

$configs = Config::GetAllConfigs();

define( "DB_HOST",$configs["database"]["OPAL_HOST"]); // Database location
define ( "DB_PORT",$configs["database"]["OPAL_PORT"]);
define( "DB_NAME",$configs["database"]["QUESTIONNAIRE_DB"]); // Database Name

// PreProd Database for the patient information
define( "DB_X_NAME",$configs["database"]["OPAL_DB"]); // Cross Database Name

define( "DB_USERNAME",$configs["database"]["OPAL_USERNAME"]); // UserName
define( "DB_PASSWORD",$configs["database"]["OPAL_PASSWORD"]); // Password

?>
