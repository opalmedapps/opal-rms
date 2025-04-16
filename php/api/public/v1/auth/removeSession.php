<?php

// SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// Logout from ORMS. Call Opaladmin V2 endpoint to flush the session

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Config;
use Orms\Http;


$response = Authentication::logout();

// provide the URL to redirect to
Http::generateResponseJsonAndExit(200, Config::getApplicationSettings()->environment->legacyOpalAdminHostExternal);
