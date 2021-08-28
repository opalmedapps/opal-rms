<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;
use Orms\Util\Encoding;

try {
    Http::parseApiInputs();
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$specialityGroups = HospitalInterface::getSpecialityGroups();
$specialityGroups = array_map(fn($x) => [
    "specialityCode" => $x["specialityCode"],
    "specialityName" => $x["specialityName"],
],$specialityGroups);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($specialityGroups));
