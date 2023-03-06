<?php

declare(strict_types=1);

//get all speciality groups and TV hubs

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;
use Orms\Util\Encoding;

try {
    Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$hubs = HospitalInterface::getHubs();

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($hubs));
