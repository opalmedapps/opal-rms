<?php

/* Script to validate logged in user's session.

Creates a session on the server using memcache and returns
the info needed for the front end to create a cookie that validates the user.

Logs the the result of logging in with Django's sessionid. */

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;
use Orms\System\Logger;


try {
    $fields = Http::parseApiInputs('v1');
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$sessionid = "";
if (isset($_COOKIE["sessionid"])) $sessionid = $_COOKIE["sessionid"];

$response = Authentication::validate($sessionid);
$content = json_decode($response->getBody()->getContents(), true);
$username = $content["username"];
$first_name = $content["first_name"];
$last_name = $content["last_name"];

if (
    $response->getStatusCode() == 200
    && isset($username)
) {
    // If authenticated and authorized, return HTTP 200 and set cookies
    $cookie = Authentication::createUserSession($username);
    Logger::logLoginEvent($username, $first_name.' '.$last_name, $response->getStatusCode(), null);
    Http::generateResponseJsonAndExit(200, data: $cookie);
}
else {
    // Return "Authentication failure" for any other errors
    $error = "Authentication failure.";
    Logger::logLoginEvent($username, $first_name.' '.$last_name, 406, $error);
    Http::generateResponseJsonAndExit(
        httpCode: 406,
        error: $error
    );
}
