<?php declare(strict_types = 1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;

$filter = $_GET["filter"] ?? NULL;

echo json_encode(DiagnosisInterface::getDiagnosisCodeList($filter));
