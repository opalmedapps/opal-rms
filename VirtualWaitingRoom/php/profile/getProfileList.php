<?php declare(strict_types=1);
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Database;

//get webpage parameters
$category = utf8_decode_recursive($_GET['category'] ?? NULL);
$speciality = utf8_decode_recursive($_GET['speciality'] ?? NULL) ;

//connect to db
$dbh = Database::getOrmsConnection();

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
$query = $dbh->prepare($sql);
$query->execute();

//encode and return the json object
$json = utf8_encode_recursive($query->fetchAll());
echo json_encode($json);

?>
