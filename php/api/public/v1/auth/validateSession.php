<?php

// SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

/* Script to validate logged in user's session.

Creates a session on the server using memcache and returns ORMS cookies to the caller.

Logs the the result of Django's sessionid validation. */

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Config;
use Orms\Http;
use Orms\System\Logger;



try {
    $opalAdminHost = Config::getApplicationSettings()->environment->legacyOpalAdminHostExternal;
    $sessionid = isset($_COOKIE["sessionid"]) ? $_COOKIE["sessionid"] : "";

    $response = Authentication::validate($sessionid);

    if (!$response || $response->getStatusCode() != 200) {
        $error = $response->getBody()->getContents();
        Logger::logLoginEvent(
            $sessionid,
            "",
            $response->getStatusCode(),
            $error
        );

        // convert 403: Authentication credentials were not provided into 401 to distinguish between not authenticated and not authorized
        if ($response->getStatusCode() == 403 && json_decode($error, true)["detail"] == "Authentication credentials were not provided.") {
            Http::generateResponseJsonAndExit(
                httpCode: 401,
                data: $opalAdminHost,
                error: $error,
            );
        } else {
            Http::generateResponseJsonAndExit(
                httpCode: $response->getStatusCode(),
                error: $error,
            );
        }
    }

    // If the user has a valid session in the backend, establish the ORMS session
    $content = json_decode($response->getBody()->getContents(), true);
    $username = $content["username"];
    $first_name = $content["first_name"] ?? '';
    $last_name = $content["last_name"] ?? '';

    Authentication::createUserSession($username, $sessionid);
    Logger::logLoginEvent(
        $username,
        $first_name . " " . $last_name,
        $response->getStatusCode(),
        null,
    );

    setcookie(
        name: "ormsAuth",
        value: $sessionid,
        path: "/",
        httponly: true,
    );
    Http::generateResponseJsonAndExit(200);

} catch (\Throwable $th) {
    Http::generateResponseJsonAndExit(
        httpCode: 500,
        error: $th->getMessage(),
    );
}
