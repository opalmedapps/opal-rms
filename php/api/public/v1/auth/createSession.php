<?php

/* Script to authenticate a user trying to log into ORMS.

Creates a session on the server using memcache and returns the info needed for the front end
to create a cookie that validates the user. */

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use GuzzleHttp\Cookie\SetCookie as CookieParser;
use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;


try {
    $fields = Http::parseApiInputs('v1');
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
    $response
    && $response->getStatusCode() == 200
    && isset($content["key"])
    && isset($response->getHeaders()["Set-Cookie"])
) {
    // If authenticated and authorized, return HTTP 200 and set cookies
    $ormsSession = Authentication::createUserSession($user->username);

    // Iterate through all the received cookies from the new backend and add them to the response.
    // Set csrftoken and sessionid cookies if user logins through the ORMS login page.
    // Otherwise user already have these tokens by logging in via opalAdmin.
    $cookieParser = new CookieParser();
    $cookiesArr = $response->getHeaders()["Set-Cookie"];

    foreach ($cookiesArr as $cookieStr) {
        $cookieDict = $cookieParser->fromString($cookieStr)->toArray();
        setcookie(
            name: $cookieDict["Name"],
            value: $cookieDict["Value"],
            expires_or_options: $cookieDict["Expires"],
            path: $cookieDict["Path"],
            secure: $cookieDict["Secure"],
            httponly: $cookieDict["HttpOnly"],
        );
    }

    // Set ormsAuth session cookie
    // TODO: remove ormsAuth cookie and use for memcache only sessionid (or API token)
    setcookie(
        name: $ormsSession["name"],
        value: $ormsSession["key"],
        path: "/",
        httponly: true,
    );

    Http::generateResponseJsonAndExit(200);
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
