<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();
$params = Encoding::utf8_decode_recursive($params);

$profileSer             = $params["profileSer"];
$profileId              = $params["profileId"];
$speciality             = $params["speciality"];
$category               = $params["category"];
$clinicalArea           = $params["clinicalArea"];
$clinics                = $params["clinics"];
$examRooms              = $params["examRooms"];
$intermediateVenues     = $params["intermediateVenues"];
$columns                = $params["columns"];

//connect to db
$dbh = Database::getOrmsConnection();

//if the profile is a new one, we have to create it
//if not, we just update an existing profile with new information

if($profileSer === -1)
{
    $queryCreateProfile = $dbh->prepare("CALL SetupProfile(?,?,?);");
    $queryCreateProfile->execute([$profileId,$speciality,$clinicalArea]);

    //the subroutine returns the new profile's serial so we update ours
    $row = $queryCreateProfile->fetchAll()[0];

    $queryCreateProfile->closeCursor();
    $profileSer = (int) $row["@profileSer := ProfileSer"];
}

//insert the profile properties
$queryProperties = $dbh->prepare("
    UPDATE Profile
    SET
        ProfileId           = :profileId,
        Category            = :category,
        SpecialityGroupId   = :speciality
    WHERE
        ProfileSer = :profileSer
");
$queryProperties->execute([
    ":profileId"     => $profileId,
    ":category"      => $category,
    ":speciality"    => $speciality,
    ":profileSer"    => $profileSer
]);
$queryProperties->closeCursor();

//insert the profile options

//first create an option string to give to the query
$optionNameString = implode("|||",array_merge($examRooms,$intermediateVenues,$clinics));
$optionTypeString = implode("|||",array_merge(
    array_fill(0,count($examRooms),"ExamRoom"),
    array_fill(0,count($intermediateVenues),"IntermediateVenue"),
    array_fill(0,count($clinics),"Resource"),
));

$queryOptions = $dbh->prepare("CALL UpdateProfileOptions(?,?,?)");
$queryOptions->execute([$profileId,$optionNameString,$optionTypeString]);
$queryOptions->closeCursor();

//insert the profile columns
$columnNameString = implode("|||", $columns);

$queryColumns = $dbh->prepare("CALL UpdateProfileColumns(?,?)");
$queryColumns->execute([$profileId,$columnNameString]);
$queryColumns->closeCursor();
