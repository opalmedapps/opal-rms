<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\User\ProfileInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$category   = Encoding::utf8_decode_recursive($params["category"] ?? null);
$speciality = Encoding::utf8_decode_recursive($params["speciality"] ?? null);

$speciality = is_string($speciality) ? (int) $speciality : null;

$profiles = ProfileInterface::getProfileList($category,$speciality);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($profiles));
