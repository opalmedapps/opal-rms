<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;

$postParams = Http::getRequestContents();

$username = $postParams["username"] ?? null;
$password = $postParams["password"] ?? null;

if(
    $username === null
    || $password === null
    || Authentication::login($username, $password)->getStatusCode() != 200
)
{
    http_response_code(406);
    exit;
}

http_response_code(200);
