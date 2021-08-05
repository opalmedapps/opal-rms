<?php declare(strict_types = 1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Util\Encoding;
use Orms\Authentication;

//script to authenticate a user trying to log into ORMS
//creates a session on the server using memcache and returns the info needed for the front end to create a cookie that validates the user

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
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

if(Authentication::validateUserCredentials($user->username,$user->password) === FALSE) {
    Http::generateResponseJsonAndExit(406);
}

$cookie = Authentication::createUserSession($user->username);

Http::generateResponseJsonAndExit(200,data: $cookie);
