<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$category   = Encoding::utf8_decode_recursive($params["category"] ?? null);
$speciality = Encoding::utf8_decode_recursive($params["speciality"] ?? null) ;

$bindParams = [];
$categoryFilter = "";
$specialityFilter = "";

if($category !== null) {
    $categoryFilter = "AND Category = :cat";
    $bindParams[":cat"] = $category;
}

if($speciality !== null) {
    $specialityFilter = "AND SpecialityGroupId = :spec";
    $bindParams[":spec"] = $speciality;
}

$query = Database::getOrmsConnection()->prepare("
    SELECT
        ProfileSer,
        ProfileId,
        CASE WHEN Category = 'PAB' THEN 'PAB/Clerical/Nursing' ELSE Category END AS Category
    FROM
        Profile
    WHERE
        1=1
        $categoryFilter
        $specialityFilter
    ORDER BY
        Category,
        ProfileId
");
$query->execute($bindParams);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($query->fetchAll()));
