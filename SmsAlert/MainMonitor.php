<?php

require_once __DIR__."/../VirtualWaitingRoom/php/loadConfigs.php";
require_once __DIR__."/OrmsCronAlert.php";
require_once __DIR__."/OpalCronAlert.php";
require_once __DIR__."/MessageLogAlert.php";

$ormsLogDB = new PDO(LOG_CONNECT, MYSQL_USERNAME, MYSQL_PASSWORD, $WRM_OPTIONS);
$ormsDB = new PDO(WRM_CONNECT, MYSQL_USERNAME, MYSQL_PASSWORD, $WRM_OPTIONS);
$opalDB = new PDO(OPAL_CONNECT, OPAL_USERNAME, OPAL_PASSWORD, $OPAL_OPTIONS);



?>