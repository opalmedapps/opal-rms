<?php

/* Script to validate logged in user's session.

Creates a session on the server using memcache and returns ORMS cookies to the caller.

Logs the the result of Django's sessionid validation. */

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;
use Orms\System\Logger;


$sessionid = isset($_COOKIE["sessionid"]) ? $_COOKIE["sessionid"] : "";
$csrftoken = isset($_COOKIE["csrftoken"]) ? $_COOKIE["csrftoken"] : "";

if (empty($sessionid) || empty($csrftoken))
    // Return successful response with no ORMS cookies if sessionid and csrftoken do not exist.
    Http::generateResponseJsonAndExit(
        httpCode: 400,
        error: "Missing Django's sessionid and csrftoken tokens.",
    );

$response = Authentication::validate($sessionid);

if (!$response || $response->getStatusCode() != 200) {
    $error = "Unsuccessful user's session validation.";
    Logger::logLoginEvent(
        $username,
        $first_name . ' ' . $last_name,
        $response->getStatusCode(),
        $error
    );

    // Remove sessionid cookie
    if (isset($_COOKIE['sessionid'])) {
        unset($_COOKIE['sessionid']);
        setcookie(name: 'sessionid', value: '', expires_or_options: -1, path: '/');
    }

    // Remove csrftoken cookie
    if (isset($_COOKIE['csrftoken'])) {
        unset($_COOKIE['csrftoken']);
        setcookie(name: 'csrftoken', value: '', expires_or_options: -1, path: '/');
    }

    Http::generateResponseJsonAndExit(
        httpCode: $response->getStatusCode(),
        error: $error,
    );
}

// If user's session successfully validated against Django backend, set user's ORMS cookies
$content = json_decode($response->getBody()->getContents(), true);
$username = $content["username"];
$first_name = $content["first_name"];
$last_name = $content["last_name"];

$ormsSession = Authentication::createUserSession($username);
Logger::logLoginEvent(
    $username,
    $first_name . ' ' . $last_name,
    $response->getStatusCode(),
    null,
);

// Set ormsAuth session cookie
// TODO: remove ormsAuth cookie and use for memcache only sessionid (or API token)
setcookie(
    name: $ormsSession["name"],
    value: $ormsSession["key"],
    path: "/",
    httponly: true,
);
Http::generateResponseJsonAndExit(200);
