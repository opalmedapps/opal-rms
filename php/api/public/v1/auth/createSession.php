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

    // Iterate through all the "Set-Cookie" response headers and add them to the cookies.
    foreach ($response->getHeaders() as $name => $values) {
        # Skip if the header is not "Set-Cookie"
        if ($name != 'Set-Cookie') continue;

        if (in_array('csrftoken', $values)) {
            $cookieParser = new CookieParser();
            $setCookie = $cookieParser->fromString($name . ': ' . implode('; ', $values));
            $cookie['csrftoken'] = $setCookie->getValue();
        }

        if (in_array('sessionid', $values)) {
            $cookieParser = new CookieParser();
            $setCookie = $cookieParser->fromString($name . ': ' . implode('; ', $values));
            $cookie['sessionid'] = $setCookie->getValue();
        }
    }

    Http::generateResponseJsonAndExit(200, data: $cookie);
}
else {
    Http::generateResponseJsonAndExit(406);
}
