<?php
//====================================================================================
// getLocationInfo.php - php code to query the MySQL database and extract information
// regarding a given intermediate venue location
//====================================================================================
require("loadConfigs.php");

// Create DB connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Extract the webpage parameters
$Location = utf8_decode_recursive($_GET["Location"]);

$sql = "
  SELECT
	IntermediateVenue.ScreenDisplayName,
	IntermediateVenue.VenueEN,
	IntermediateVenue.VenueFR
  FROM
	IntermediateVenue
  WHERE
	IntermediateVenue.AriaVenueId = '$Location'";

/* Process results */
$json = [];
$query = $dbWRM->query($sql);

$rows = $query->fetchAll(PDO::FETCH_ASSOC);

// if no rows were returned then search for this venue in the ExamRoom table
if(count($rows) == 0)
{
	$sql = "
		SELECT
			ExamRoom.ScreenDisplayName,
			ExamRoom.VenueEN,
			ExamRoom.VenueFR
		FROM
			ExamRoom
		WHERE
			ExamRoom.AriaVenueId = '$Location'";

	$query = $dbWRM->query($sql);

	$rows =  $query->fetchAll(PDO::FETCH_ASSOC);
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

$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
