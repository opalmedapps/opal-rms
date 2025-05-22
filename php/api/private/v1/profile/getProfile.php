<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);
//script to get the page settings for a profile

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Config;
use Orms\Hospital\HospitalInterface;
use Orms\Http;
use Orms\User\ProfileInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$profileId   = $params["profileId"] ?? null;
$clinicHubId = (int) ($params["clinicHubId"] ?? null);

if($profileId === null) {
    Http::generateResponseJsonAndExit(400, data: (object) []);
}

$clinicHubs = array_merge(...array_values(HospitalInterface::getHubs()));
$clinicHub = array_values(array_filter($clinicHubs,fn($x) => $x["clinicHubId"] === $clinicHubId))[0] ?? null;

$profile = ProfileInterface::getProfile($profileId);

if($profile === null || $clinicHub === null) {
    Http::generateResponseJsonAndExit(400, data: (object) []);
}

$configs = Config::getApplicationSettings();

$options = array_map(fn($x) => [
    "Name" => $x["name"],
    "Type" => $x["type"]
],ProfileInterface::getProfileOptions($profileId));

$columns = array_map(fn($x) => [
    "ColumnName"    => $x["columnName"],
    "DisplayName"   => $x["displayName"],
    "Glyphicon"     => $x["glyphicon"],
    "Position"      => $x["position"],
],ProfileInterface::getProfileColumns($profileId));

$profileDetails = [
    "ProfileSer"           => $profile["profileSer"],
    "ProfileId"            => $profileId,
    "Category"             => $profile["category"],
    "Speciality"           => $profile["specialityGroupId"],
    "ClinicalArea"         => $clinicHub["clinicHubName"],
    "ClinicHubId"          => $clinicHubId,
    "WaitingRoom"          => mb_strtoupper($clinicHub["clinicHubName"]) ." WAITING ROOM",
    "Resources"            => array_values(array_filter($options,fn($x) => $x["Type"] === "Resource")),
    "Locations"            => array_values(array_filter($options,fn($x) => $x["Type"] === "ExamRoom" || $x["Type"] === "IntermediateVenue")),
    "ColumnsDisplayed"     => $columns,
    "sortOrder"            => in_array($profile["category"],["PAB","Treatment Machine","Physician"]) ? ["ScheduledStartTime_hh","ScheduledStartTime_mm"] : "LastName", //set the load order of the appointments on the vwr page depending the category
    "FirebaseConfig"       => $configs->environment->firebaseConfig,
    "FirebaseBranch"       => $configs->environment->firebaseBranch,
    "CheckInFile"          => "tmp/$profile[specialityGroupId].vwr.json"
];

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($profileDetails));
