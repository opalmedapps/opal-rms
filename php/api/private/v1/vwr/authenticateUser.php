<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;

$postParams = Http::getRequestContents();

$username = $postParams["username"] ?? null;
$password = $postParams["password"] ?? null;

if(
    $username === null
    || $password === null
    || Authentication::validateUserCredentials($username, $password) === false
)
{
    http_response_code(406);
    exit;
}

http_response_code(200);
