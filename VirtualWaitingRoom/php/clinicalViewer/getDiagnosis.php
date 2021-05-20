<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DiagnosisInterface;

$diagnosisList = array_map(function($x) {
    return [
        "Name"    => $x->subcode ."-". $x->subcodeDescription,
        "subcode" => $x->subcode
    ];
},DiagnosisInterface::getUsedDiagnosisCodeList());

$resources = Encoding::utf8_encode_recursive($diagnosisList);
echo json_encode($diagnosisList);
