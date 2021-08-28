<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;

$sites = array_map(fn($x) => [
    "HospitalCode" => $x["hospitalCode"],
    "HospitalName" => $x["hospitalName"],
],HospitalInterface::getHospitalSites());

Http::generateResponseJsonAndExit(200, data: $sites);
