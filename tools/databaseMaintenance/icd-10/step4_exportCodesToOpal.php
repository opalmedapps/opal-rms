<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Opal;
use Orms\DiagnosisInterface;

//get list of diagnosis codes in database
$diagList = DiagnosisInterface::getDiagnosisCodeList();

//export them to Opal
foreach($diagList as $d) {
    Opal::exportDiagnosisCode($d->id,$d->subcode,$d->subcodeDescription);
}

?>
