<?php

declare(strict_types=1);

require __DIR__ ."/../../../../../vendor/autoload.php";

use Orms\Labs\LabsInterface;
use Orms\Http;

$params = Http::getRequestContents();

$patientId = $params["patientId"] ?? null;

Http::generateResponseJsonAndExit(200,data: LabsInterface::getLabsListForPatient($patientId));
