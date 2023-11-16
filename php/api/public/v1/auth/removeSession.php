<?php

// Logout from ORMS. Call Opaladmin V2 endpoint to flush the session

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;


$sessionid = isset($_COOKIE["sessionid"]) ? $_COOKIE["sessionid"] : "";
$csrftoken = isset($_COOKIE["csrftoken"]) ? $_COOKIE["csrftoken"] : "";

if (!empty($csrftoken) && !empty($sessionid)) {
    // If csrftoken and sessionid cookies are not empty, logout from the Django backend.
    $response = Authentication::logout($csrftoken, $sessionid);
    if (!$response || $response->getStatusCode() != 200) {
        // TODO: log unsuccessful logout
    }
}

// TODO: remove session from memcache

Http::generateResponseJsonAndExit(200);
