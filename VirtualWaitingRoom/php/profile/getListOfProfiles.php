<?php declare(strict_types = 1);
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

$speciality = $_GET['speciality'] ?? NULL;

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
        Profile.ClinicalArea,
        CASE WHEN Profile.Category = 'PAB' THEN 'PAB/Clerical/Nursing' ELSE Profile.Category END AS Category
    FROM
        Profile
    WHERE
        Profile.Speciality = ?
    ORDER BY
        Profile.Category,
        Profile.ProfileId";

//process results
$query = $dbh->prepare($sql);
$query->execute([$speciality]);

while($row = $query->fetch())
{
    $json[] = $row;
}

$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
