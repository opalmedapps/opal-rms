<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\External\LEGACY_OA\Fetch;
use Orms\Http;

Http::generateResponseJsonAndExit(200, data: Fetch::getListOfQuestionnaires());
