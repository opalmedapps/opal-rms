<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use GuzzleHttp\Cookie\SetCookie as CookieParser;
use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;

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
$csrftoken = "";
if(isset($_COOKIE["csrftoken"])) {
    $csrftoken = $_COOKIE["csrftoken"];
}


try {
    $response = Authentication::validate($sessionid);
    $content = json_decode($response->getBody()->getContents(), true);

    if (
        $response->getStatusCode() == 200
        && isset($content["username"])
    ) {
        // If authenticated and authorized, return HTTP 200 and set cookies
        $cookie = Authentication::createUserSession($content['username']);
        $CookieInfo = session_get_cookie_params();
        $cookie["sessionid"]["value"] = $sessionid;
        $cookie["sessionid"]["params"] = [
            "path" => $CookieInfo["path"],
            "domain" => $CookieInfo["domain"],
            "expires" => date("Wdy, DD Mon YYYY HH:MM:SS GMT", $CookieInfo["lifetime"]),
            "samesite" => $CookieInfo["samesite"],
        ];

        $cookie["csrftoken"]["value"] = $csrftoken;
        $cookie["csrftoken"]["params"] = [
            "path" => $CookieInfo["path"],
            "domain" => $CookieInfo["domain"],
            "expires" => date("Wdy, DD Mon YYYY HH:MM:SS GMT", $CookieInfo["lifetime"]),
            "samesite" => $CookieInfo["samesite"],
        ];

        Http::generateResponseJsonAndExit(200, data: $cookie);
    }
    elseif ($response->getStatusCode() == 403) {
        // If user is unauthorized (HTTP 403) return a human-readable error message
        Http::generateResponseJsonAndExit(
            httpCode: 403,
            error: "You do not have permission to access ORMS. Contact the Opal systems administrator."
        );
    }
    else {
        // Return "Authentication failure" for any other errors
        Http::generateResponseJsonAndExit(
            httpCode: 406,
            error: "Authentication failure"
        );
    }
}
catch(Exception $e) {
    Http::generateResponseJsonAndExit(
        httpCode: 403,
        error: $e->getMessage()
    );
}
