<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\External\OIE\Fetch;
use Orms\Http;

$purposes = Fetch::getQuestionnairePurposes();

Http::generateResponseJsonAndExit(200, data: $purposes);
