<?php
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

//get webpage parameters
$category = utf8_decode_recursive($_GET['category']);
$speciality = utf8_decode_recursive($_GET['speciality']);

$json = []; //output array

//connect to db
$dbh = Config::getDatabaseConnection("ORMS");

//==================================
//get profiles
//==================================
$sql = "
    SELECT
        Profile.ProfileSer,
        Profile.ProfileId,
        Profile.ClinicalArea
    FROM
        Profile
    WHERE
        Profile.Category = '$category'
        AND Profile.Speciality = '$speciality'";

if(!$category && !$speciality)
{
    $sql = "
        SELECT
            Profile.ProfileSer,
            Profile.ProfileId,
            Profile.ClinicalArea
        FROM
            Profile";
}

//process results
$query = $dbh->query($sql);

while($row = $query->fetch())
{
    $json[] = $row;
}

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
