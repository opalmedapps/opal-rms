<?php

declare(strict_types=1);
//====================================================================================
// php code to query the database and
// extract the list of options, which includes clinics and rooms
//====================================================================================

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$clinicHub  = $params["clinicHub"];

$json = []; //json object to be returned

$intermediateVenues = [];
$examRooms = [];

$query = Database::getOrmsConnection()->prepare("
    SELECT
        LTRIM(RTRIM(AriaVenueId)) AS Name,
        'ExamRoom' AS Type
    FROM
        ExamRoom
    WHERE
        ClinicHubId = :id
    UNION
    SELECT
        LTRIM(RTRIM(AriaVenueId)) AS Name,
        'IntermediateVenue' AS Type
    FROM
        IntermediateVenue
    WHERE
        ClinicHubId = :id
");
$query->execute([":id" => $clinicHub]);

$rooms = $query->fetchAll();
usort($rooms,fn($a,$b) => [$a["Type"],$a["Name"]] <=> [$b["Type"],$b["Name"]]);

$rooms = Encoding::utf8_encode_recursive($rooms);

Http::generateResponseJsonAndExit(200, data: $rooms);
