<?php

declare(strict_types=1);
//script to get the page settings for a profile

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Config;
use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$profileId   = $params["profileId"];
$clinicHubId = (int) $params["clinicHubId"];

$configs = Config::getApplicationSettings();
$dbh = Database::getOrmsConnection();

//==================================
//get profile
//==================================
$queryProfile = $dbh->prepare("
    SELECT
        ProfileSer,
        ProfileId,
        Category,
        SpecialityGroupId AS Speciality,
        (SELECT ClinicHubName FROM ClinicHub WHERE ClinicHubId = :chId) AS ClinicalArea
    FROM
        Profile
    WHERE
        ProfileId = :proId
");
//process results
$queryProfile->execute([
    ":proId" => $profileId,
    ":chId"  => $clinicHubId
]);

$profile = $queryProfile->fetchAll()[0] ?? null;

/** @phpstan-ignore-next-line */
if($profile === null || $profile["ClinicalArea"] === null) {
    Http::generateResponseJsonAndExit(400, data: (object) []);
}

$profileDetails = [
    "ProfileSer"           => (int) $profile["ProfileSer"],
    "ProfileId"            => $profile["ProfileId"],
    "Category"             => $profile["Category"],
    "Speciality"           => (int) $profile["Speciality"],
    "ClinicalArea"         => $profile["ClinicalArea"],
    "ClinicHubId"          => $clinicHubId,
    "WaitingRoom"          => mb_strtoupper($profile["ClinicalArea"]) ." WAITING ROOM",
    "Resources"            => [],
    "ColumnsDisplayed"     => [],
    "sortOrder"            => in_array($profile["Category"],["PAB","Treatment Machine","Physician"]) ? ["ScheduledStartTime_hh","ScheduledStartTime_mm"] : "LastName", //set the load order of the appointments on the vwr page depending the category
    "FirebaseUrl"          => $configs->environment->firebaseUrl,
    "FirebaseSecret"       => $configs->environment->firebaseSecret,
    "CheckInFile"          => $configs->environment->baseUrl ."/tmp/$profile[Speciality].vwr.json"
];

//==========================================================
//get the profile columns
//==========================================================
$queryColumns = $dbh->prepare("
    SELECT
        PCD.ColumnName,
        PCD.DisplayName,
        PCD.Glyphicon,
        PC.Position
    FROM
        ProfileColumns PC
        INNER JOIN ProfileColumnDefinition PCD ON PCD.ProfileColumnDefinitionSer = PC.ProfileColumnDefinitionSer
    WHERE
        PC.ProfileSer = ?
        AND PC.Position >= 0
        AND PC.Active = 1
    ORDER BY
        PC.Position
");
$queryColumns->execute([$profileDetails["ProfileSer"]]);

$profileDetails["ColumnsDisplayed"] = $queryColumns->fetchAll();

//=======================================================
//next get the profile options
//=======================================================
$queryOptions = $dbh->prepare("
    SELECT
        Options,
        Type
    FROM
        ProfileOptions
    WHERE
        ProfileSer = ?
    ORDER BY
        Options
");
$queryOptions->execute([$profileDetails["ProfileSer"]]);

$options = array_map(fn($x) => [
    "Name"  => $x["Options"],
    "Type"  => $x["Type"]
],$queryOptions->fetchAll());

$profileDetails["Resources"] = array_values(array_filter($options,fn($x) => $x["Type"] === "Resource"));
$profileDetails["Locations"] = array_values(array_filter($options,fn($x) => $x["Type"] === "ExamRoom" || $x["Type"] === "IntermediateVenue"));

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($profileDetails));
