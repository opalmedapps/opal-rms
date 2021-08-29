<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;

$params = Http::getRequestContents();

$filename   = $params["filename"];
$identifier = $params["identifier"];
$type       = $params["type"];
$message    = $params["message"];


Http::generateResponseJsonAndExit(200);
