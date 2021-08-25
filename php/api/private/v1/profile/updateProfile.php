<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\User\ProfileInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();
$params = Encoding::utf8_decode_recursive($params);

$profileSer             = $params["profileSer"];
$profileId              = $params["profileId"];
$speciality             = (int) $params["speciality"];
$category               = $params["category"];
$options                = $params["options"];
$columns                = $params["columns"];

//if the profile is a new one, we have to create it
//if not, we just update an existing profile with new information

if($profileSer === -1)
{
    $profileSer = ProfileInterface::createProfile($profileId,$speciality);
}

ProfileInterface::updateProfile(
    profileSer:             $profileSer,
    profileId:              $profileId,
    category:               $category,
    specialityGroupId:      $speciality,
    options:                $options,
    columns:                $columns,
);

Http::generateResponseJsonAndExit(200);
