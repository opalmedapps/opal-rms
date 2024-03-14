<?php

// Logout from ORMS. Call Opaladmin V2 endpoint to flush the session

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Authentication;
use Orms\Config;
use Orms\Http;


$response = Authentication::logout();

// provide the URL to redirect to
Http::generateResponseJsonAndExit(200, Config::getApplicationSettings()->environment->opalAdminHost);
