<?php declare(strict_types = 1);
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$speciality = $_GET['speciality'] ?? NULL;

//connect to db
$dbh = Database::getOrmsConnection();

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

$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);
