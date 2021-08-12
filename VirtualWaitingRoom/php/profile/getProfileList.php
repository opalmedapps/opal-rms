<?php

declare(strict_types=1);
//script to get a list of profiles in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Util\Encoding;

//get webpage parameters
$category = Encoding::utf8_decode_recursive($_GET["category"] ?? null);
$speciality = Encoding::utf8_decode_recursive($_GET["speciality"] ?? null) ;

//connect to db
$dbh = Database::getOrmsConnection();

//get profiles
if($category === null || $speciality === null) {
    $query = $dbh->prepare("
        SELECT
            Profile.ProfileSer,
            Profile.ProfileId
        FROM
            Profile
    ");
    $query->execute();
}
else {
    $query = $dbh->prepare("
        SELECT
            ProfileSer,
            ProfileId
        FROM
            Profile
        WHERE
            Category = :cat
            AND SpecialityGroupId = :spec
    ");
    $query->execute([
        ":cat"  => $category,
        ":spec" => $speciality
    ]);
}

//encode and return the json object
$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);
