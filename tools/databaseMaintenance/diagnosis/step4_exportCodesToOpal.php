<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Hospital\OIE\Export;

//get list of diagnosis codes in database
$diagList = DiagnosisInterface::getDiagnosisCodeList();

//export them to Opal
foreach($diagList as $d) {
    Export::exportDiagnosisCode($d->subcode,$d->subcodeDescription);
}
