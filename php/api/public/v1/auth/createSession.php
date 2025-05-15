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

if ($response->getStatusCode() == 200 && isset($content["key"])) {
    $cookie = Authentication::createUserSession($user->username);

    $setCookieHeader = $response->getHeaders()["Set-Cookie"];
    $cookieParser = new CookieParser();
    $csrftoken = $cookieParser->fromString($setCookieHeader[0])->toArray();
    $sessionid = $cookieParser->fromString($setCookieHeader[1])->toArray();
    $cookie["csrftoken"]["value"] = $csrftoken["Value"];
    $cookie["csrftoken"]["params"] = [
        "path" => $csrftoken["Path"],
        "domain" => $csrftoken["Domain"],
        "expires" => date("Wdy, DD Mon YYYY HH:MM:SS GMT", $csrftoken["Expires"]),
        // "secure" => $csrftoken["HttpOnly"],
        "samesite" => $csrftoken["SameSite"],
    ];
    $cookie['sessionid']['value'] = $sessionid['Value'];
    $cookie['sessionid']['params'] = [
        "path" => $sessionid["Path"],
        "domain" => $sessionid["Domain"],
        "expires" => date("Wdy, DD Mon YYYY HH:MM:SS GMT", $sessionid["Expires"]),
        // "secure" => $sessionid["HttpOnly"],
        "samesite" => $sessionid["SameSite"],
    ];

    Http::generateResponseJsonAndExit(200, data: $cookie);
}
else {
    Http::generateResponseJsonAndExit(406);
}
