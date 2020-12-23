<?php
//script to delete a profile in the WRM database

require("../loadConfigs.php");

//get webpage parameters
$profileId = utf8_decode_recursive($_GET['profileId']);

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//call the delete stored procedure

$sqlDeleteProfile = "CALL DeleteProfile('$profileId');";

echo $sqlDeleteProfile;

$queryDeleteProfile = $dbWRM->query($sqlDeleteProfile);

if($queryDeleteProfile) {echo "Profile deleted";}

$dbWRM = null;

?>
