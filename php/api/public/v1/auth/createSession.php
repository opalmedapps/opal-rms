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
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$user = new class(
    username:     $fields["username"],
    password:     $fields["password"],
) {
    public function __construct(
        public string $username,
        public string $password,
    ) {}
};

$response = Authentication::login($user->username, $user->password);
$content = json_decode($response->getBody()->getContents(), true);

if (
    $response->getStatusCode() == 200
    && isset($content["key"])
    && isset($response->getHeaders()["Set-Cookie"])
) {
    // If authenticated and authorized, return HTTP 200 and set cookies
    $cookie = Authentication::createUserSession($user->username);
    $cookieParser = new CookieParser();
    $cookiesArr = $response->getHeaders()["Set-Cookie"];

    // Iterate through all the received cookies from the new backend and add them to the response.
    foreach ($cookiesArr as $cookieStr) {
        $cookieDict = $cookieParser->fromString($cookieStr)->toArray();
        $name = $cookieDict["Name"];
        $cookie[$name]["value"] = $cookieDict["Value"];
        $cookie[$name]["params"] = [
            "path" => $cookieDict["Path"],
            "domain" => $cookieDict["Domain"],
            "expires" => date("Wdy, DD Mon YYYY HH:MM:SS GMT", $cookieDict["Expires"]),
            // "secure" => $cookieDict["HttpOnly"],
            "samesite" => $cookieDict["SameSite"],
        ];
    }

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
    Http::generateResponseJsonAndExit(406);
}
