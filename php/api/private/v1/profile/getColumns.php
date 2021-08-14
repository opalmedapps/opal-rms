<?php

declare(strict_types=1);
//script to get a list of patient columns in the WRM database

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$speciality = $params["speciality"];

$query = Database::getOrmsConnection()->prepare("
    SELECT
        ProfileColumnDefinition.ColumnName,
        ProfileColumnDefinition.DisplayName,
        ProfileColumnDefinition.Glyphicon,
        ProfileColumnDefinition.Description
    FROM
        ProfileColumnDefinition
    WHERE
        (ProfileColumnDefinition.Speciality = 'All'
            OR ProfileColumnDefinition.Speciality = ?)
    ORDER BY ProfileColumnDefinition.ColumnName");
$query->execute([$speciality]);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($query->fetchAll()));
