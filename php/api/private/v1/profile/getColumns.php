<?php

declare(strict_types=1);
//script to get a list of patient columns in the WRM database

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\User\ProfileInterface;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$columns = ProfileInterface::getColumnDefinitions();

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($columns));
