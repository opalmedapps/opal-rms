<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Hospital\OIE\Internal\Connection;
use Orms\DiagnosisInterface;

//get list of diagnosis codes in database
$diagList = DiagnosisInterface::getDiagnosisCodeList();

//export them to Opal
foreach($diagList as $d) {
    exportDiagnosisCode($d->id,$d->subcode,$d->subcodeDescription);
}

function exportDiagnosisCode(int $id,string $code,string $desc): void
{
    Connection::getOpalHttpClient()?->request("POST","master-source/insert/diagnoses",[
        "form_params" => [
            [
                "source"        => "ORMS",
                "externalId"    => $id,
                "code"          => $code,
                "description"   => $desc
            ]
        ]
    ]);
}

?>
