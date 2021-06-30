<?php declare(strict_types = 1);
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;

$speciality = $_GET['speciality'] ?? NULL;

//connect to db
$dbh = Database::getOrmsConnection();

//==================================
//get profiles
//==================================
$sql = "
    SELECT
        P.ProfileSer,
        P.ProfileId,
        CASE WHEN P.Category = 'PAB' THEN 'PAB/Clerical/Nursing' ELSE P.Category END AS Category
    FROM
        Profile P
    WHERE
        P.SpecialityGroupId = ?
    ORDER BY
        P.Category,
        P.ProfileId";

//process results
$query = $dbh->prepare($sql);
$query->execute([$speciality]);

$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);
