<?php
//script to get a list of patient columns in the WRM database

require("loadConfigs.php");

//get webpage parameters
$speciality = $_GET['speciality'];

$json = []; //output array

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//==================================
//get columns
//==================================
$sql = "
	SELECT
		ProfileColumnDefinition.ColumnName,
		ProfileColumnDefinition.DisplayName,
		ProfileColumnDefinition.Glyphicon,
		ProfileColumnDefinition.Description
	FROM
		ProfileColumnDefinition
	WHERE
		(ProfileColumnDefinition.Speciality = 'All'
			OR ProfileColumnDefinition.Speciality = '$speciality')
	ORDER BY ProfileColumnDefinition.ColumnName";


//process results
$query = $dbWRM->query($sql);

while($row = $query->fetch(PDO::FETCH_ASSOC))
{
	$json[] = $row;
}

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
