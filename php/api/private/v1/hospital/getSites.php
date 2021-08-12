<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;

$dbh = Database::getOrmsConnection();

$query = $dbh->prepare("
    SELECT
        HospitalCode,
        HospitalName
    FROM
        Hospital
");
$query->execute();

Http::generateResponseJsonAndExit(200, data: $query->fetchAll());
