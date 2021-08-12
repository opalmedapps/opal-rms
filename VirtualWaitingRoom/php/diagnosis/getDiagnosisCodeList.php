<?php

declare(strict_types=1);

require __DIR__ ."/../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;

$filter = $_GET["filter"] ?? null;

echo json_encode(DiagnosisInterface::getDiagnosisCodeList($filter));
