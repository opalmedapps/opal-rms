<?php

declare(strict_types=1);

require __DIR__ ."/../../../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Http;

$params = Http::getRequestContents();

$filter = $params["filter"] ?? null;

Http::generateResponseJsonAndExit(200,data: DiagnosisInterface::getDiagnosisCodeList($filter));
