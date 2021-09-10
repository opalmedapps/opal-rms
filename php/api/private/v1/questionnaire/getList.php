<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\External\OIE\Fetch;
use Orms\Http;

Http::generateResponseJsonAndExit(200, data: Fetch::getListOfQuestionnaires());
