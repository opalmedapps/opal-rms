<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Http;
use Orms\Util\Encoding;

//logout from the ORMS
//call the API endpoint to flush the session in the opalAdmin backend

try {
    $fields = Http::parseApiInputs('v1');
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$csrftoken = "";
if(isset($_COOKIE["csrftoken"])) {
    $csrftoken = $_COOKIE["csrftoken"];
}
$sessionid = "";
if(isset($_COOKIE["sessionid"])) {
    $sessionid = $_COOKIE["sessionid"];
}

if($csrftoken !== "" && $sessionid !== ""){
    Authentication::logout($csrftoken, $sessionid);
}
else{
    Http::generateResponseJsonAndExit(
        httpCode: 406,
        error: "CSRF token or Session Id is missing."
    );
}
