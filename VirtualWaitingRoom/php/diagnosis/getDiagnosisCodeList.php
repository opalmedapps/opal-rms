<?php declare(strict_types = 1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;

echo json_encode(array_slice(DiagnosisInterface::getDiagnosisCodeList(),0,10));

?>
