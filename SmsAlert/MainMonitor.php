<?php
/**
 * This script is for monitoring the cronjobs and other possible database error in Opal or Orms.
 * It will send an email if any alert class got an error.
 */
//import package
require_once __DIR__."/../VirtualWaitingRoom/php/loadConfigs.php";
require_once __DIR__."/OrmsCronAlert.php";
require_once __DIR__."/OpalCronAlert.php";
require_once __DIR__."/MessageLogAlert.php";
//email address
$email = "zeyu.dou@mail.mcgill.ca, victor.matassa@muhc.mcgill.ca, yickkuan.mo@muhc.mcgill.ca";
$header = "CC: susie.judd@muhc.mcgill.ca,John.Kildea@muhc.mcgill.ca \r\n";
//PDO database connection
$ormsLogDB = new PDO(LOG_CONNECT, MYSQL_USERNAME, MYSQL_PASSWORD, $WRM_OPTIONS);
$ormsDB = new PDO(WRM_CONNECT, MYSQL_USERNAME, MYSQL_PASSWORD, $WRM_OPTIONS);
$opalDB = new PDO(OPAL_CONNECT, OPAL_USERNAME, OPAL_PASSWORD, $OPAL_OPTIONS);
//Create new alert modules
$ormsCronjob = new OrmsCronAlert();
$opalCronjob = new OpalCronAlert();
$messageLog = new MessageLogAlert();
//Check status
$checkOrmsCron = $ormsCronjob ->SendMail($ormsDB);
$checkOpalCron = $opalCronjob ->SendMail($opalDB);
$checkMessageLog = $messageLog ->SendMail($ormsLogDB);

//Send email if there is error
if(!($checkOrmsCron and $checkOpalCron and $checkMessageLog)){
    $message = "Hi there,\n\n The monitoring system got an unknown error. Please check.\n\nThanks.\n";
    mail($email, OTHER_ERROR, $message,$header);
}

?>