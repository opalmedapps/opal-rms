<?php

declare(strict_types=1);

//-------------------------------------------------
// Returns a list of resources depending on the speciality specified
//-------------------------------------------------

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$speciality = (int) ($params["speciality"] ?? null);

$query = Database::getOrmsConnection()->prepare("
    SELECT DISTINCT
        COALESCE(DisplayName,AppointmentCode) AS AppointmentCode
    FROM
        AppointmentCode
    WHERE
        SpecialityGroupId = ?
    ORDER BY
        AppointmentCode
");
$query->execute([$speciality]);

$codes = Encoding::utf8_encode_recursive($query->fetchAll());
Http::generateResponseJsonAndExit(200, data: $codes);
