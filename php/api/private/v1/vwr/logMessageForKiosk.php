<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\System\Logger;

$params = Http::getRequestContents();

$input            = $params["input"];
$location         = $params["location"];
$destination      = $params["destination"];
$centerImage      = $params["centerImage"];
$arrowDirection   = $params["arrowDirection"];
$message          = $params["message"];

Logger::logKioskEvent($input,$location,$destination,$centerImage,$arrowDirection,$message);

Http::generateResponseJsonAndExit(200);
