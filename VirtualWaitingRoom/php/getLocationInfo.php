<?php declare(strict_types=1);
//====================================================================================
// php code to query the MySQL database and extract information
// regarding a given room location
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;
use Orms\Http;

// Extract the webpage parameters
$location = Encoding::utf8_decode_recursive($_GET["Location"]);

$dbh = Database::getOrmsConnection();
$query = $dbh->prepare("
    SELECT
        ScreenDisplayName,
        VenueEN,
        VenueFR
    FROM
        IntermediateVenue
    WHERE
        AriaVenueId = :location
    UNION ALL
    SELECT
        ScreenDisplayName,
        VenueEN,
        VenueFR
    FROM
        ExamRoom
    WHERE
        AriaVenueId = :location
");
$query->execute([":location" => $location]);

$room = $query->fetchAll()[0] ?? NULL;

if($room === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown room");
}

Http::generateResponseJsonAndExit(200,data: Encoding::utf8_encode_recursive($room));
