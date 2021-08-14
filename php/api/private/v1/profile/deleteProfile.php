<?php

declare(strict_types=1);
//script to delete a profile in the WRM database

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$profileId = Encoding::utf8_decode_recursive($params["profileId"]);

//call the delete stored procedure
$query = Database::getOrmsConnection()->prepare("CALL DeleteProfile(?);");
$query->execute([$profileId]);

Http::generateResponseJsonAndExit(200);
