<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Http;
use Orms\Hospital\OIE\Fetch;

$purposes = Fetch::getQuestionnairePurposes();

Http::generateResponseJsonAndExit(200,data: $purposes);
