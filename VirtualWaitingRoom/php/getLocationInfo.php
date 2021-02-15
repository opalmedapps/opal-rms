<?php declare(strict_types=1);
//====================================================================================
// getLocationInfo.php - php code to query the MySQL database and extract information
// regarding a given intermediate venue location
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

// Create DB connection
$dbh = Database::getOrmsConnection();

// Extract the webpage parameters
$Location = Encoding::utf8_decode_recursive($_GET["Location"]);

/* Process results */
$json = [];
$query = $dbh->prepare("
    SELECT
        IntermediateVenue.ScreenDisplayName,
        IntermediateVenue.VenueEN,
        IntermediateVenue.VenueFR
    FROM
        IntermediateVenue
    WHERE
        IntermediateVenue.AriaVenueId = '$Location'
");
$query->execute();

$rows = $query->fetchAll();

// if no rows were returned then search for this venue in the ExamRoom table
if(count($rows) === 0)
{
    $query = $dbh->prepare("
        SELECT
            ExamRoom.ScreenDisplayName,
            ExamRoom.VenueEN,
            ExamRoom.VenueFR
        FROM
            ExamRoom
        WHERE
            ExamRoom.AriaVenueId = '$Location'
    ");
    $query->execute();

    $rows = $query->fetchAll();
}

// output data of each row
foreach($rows as &$row)
{
    $json = [
        'ScreenDisplayName' => $row['ScreenDisplayName'], # Use this notation as just expecting 1 row
        'VenueEN' => $row['VenueEN'], # Use this notation as just expecting 1 row
        'VenueFR' => $row['VenueFR'] # Use this notation as just expecting 1 row
    ];
}

if(count($json) == 0)
{
    die("0 results");
}

$json = Encoding::utf8_encode_recursive($json);
echo json_encode($json);

?>
