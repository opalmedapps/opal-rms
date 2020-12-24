<?php
//script to delete a profile in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

//get webpage parameters
$profileId = utf8_decode_recursive($_GET['profileId']);

//connect to db
$dbh = Config::getDatabaseConnection("ORMS");

//call the delete stored procedure

$sqlDeleteProfile = "CALL DeleteProfile('$profileId');";

echo $sqlDeleteProfile;

$queryDeleteProfile = $dbh->query($sqlDeleteProfile);

if($queryDeleteProfile) {echo "Profile deleted";}

?>
