<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;
use Orms\System\Logger;

//script to validate a user session logged into ORMS
//creates a session on the server using memcache and returns the info needed for the front end to create a cookie that validates the user
//log the information in success or failed

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

$username = "";
$first_name = "";
$last_name = "";
try {
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
}
catch(Exception $e) {
    Logger::logLoginEvent($username, $first_name.' '.$last_name, $e->getCode(), $e->getMessage());
    Http::generateResponseJsonAndExit(
        httpCode: 403,
        error: $e->getMessage()
    );
}
