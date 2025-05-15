<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use GuzzleHttp\Cookie\SetCookie as CookieParser;
use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;
use Orms\System\Logger;

//script to authenticate a user trying to log into ORMS
//creates a session on the server using memcache and returns the info needed for the front end to create a cookie that validates the user

try {
    $fields = Http::parseApiInputs('v1');
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$sessionid = "";
if(isset($_COOKIE["sessionid"])) {
    $sessionid = $_COOKIE["sessionid"];
}

try {
    $response = Authentication::validate($sessionid);
    $content = json_decode($response->getBody()->getContents(), true);
    $username = $content["username"];
    $error = "";
    if (
        $response->getStatusCode() == 200
        && isset($username)
    ) {
        // If authenticated and authorized, return HTTP 200 and set cookies
        $cookie = Authentication::createUserSession($username);

        Http::generateResponseJsonAndExit(200, data: $cookie);
    }
    elseif ($response->getStatusCode() == 403) {
        // If user is unauthorized (HTTP 403) return a human-readable error message
        $error = "You do not have permission to access ORMS. Contact the Opal systems administrator.";
        Http::generateResponseJsonAndExit(
            httpCode: 403,
            error: $error
        );
    }
    else {
        // Return "Authentication failure" for any other errors
        $error = "Authentication failure.";
        Http::generateResponseJsonAndExit(
            httpCode: 406,
            error: $error
        );
    }

    Logger::logLoginEvent($username, $response->getStatusCode(), $error);
}
catch(Exception $e) {
    Http::generateResponseJsonAndExit(
        httpCode: 403,
        error: $e->getMessage()
    );
}
