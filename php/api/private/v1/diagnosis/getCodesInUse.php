<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Http;
use Orms\Util\Encoding;

$diagnosisList = array_map(function($x) {
    return [
        "Name"    => $x->subcode ."-". $x->subcodeDescription,
        "subcode" => $x->subcode
    ];
}, DiagnosisInterface::getUsedDiagnosisCodeList());

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($diagnosisList));
