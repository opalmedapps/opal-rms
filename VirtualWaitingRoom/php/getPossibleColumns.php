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
$query = $dbWRM->prepare("
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

//process results

while($row = $query->fetch(PDO::FETCH_ASSOC))
{
	$json[] = $row;
}

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
