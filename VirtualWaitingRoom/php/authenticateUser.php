<?php declare(strict_types = 1);

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Http;
use Orms\Authentication;

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

http_response_code(200);

?>
