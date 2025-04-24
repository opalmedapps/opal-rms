<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\System\Logger;

$params = Http::getRequestContents();

$filename   = $params["filename"];
$identifier = $params["identifier"];
$type       = $params["type"];
$message    = $params["message"];

Logger::logVwrEvent($filename,$identifier,$type,$message);

Http::generateResponseJsonAndExit(200);
