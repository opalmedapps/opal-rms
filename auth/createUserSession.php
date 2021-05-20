<?php declare(strict_types = 1);

require_once __DIR__."/../vendor/autoload.php";

use Orms\Http;
use Orms\Authentication;

//script to authenticate a user trying to log into ORMS
//creates a session on the server using memcache and returns the info needed for the front end to create a cookie that validates the user

$postParams = Http::getPostContents();

$username = $postParams["username"] ?? NULL;
$password = $postParams["password"] ?? NULL;

if(
    $username === NULL
    || $password === NULL
    || Authentication::validateUserCredentials($username,$password) === FALSE
)
{
    http_response_code(406);
    exit;
}

$cookie = Authentication::createUserSession($username);

http_response_code(200);
echo json_encode($cookie);
