<?php

declare(strict_types=1);
//script to delete a profile in the WRM database

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\User\ProfileInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$profileId = Encoding::utf8_decode_recursive($params["profileId"]);

ProfileInterface::deleteProfile($profileId);

Http::generateResponseJsonAndExit(200);
