<?php
//script to get a list of patient columns in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";
require("../loadConfigs.php");

use Orms\Config;

//get webpage parameters
$speciality = $_GET['speciality'];

$json = []; //output array

//connect to db
$dbh = Config::getDatabaseConnection("ORMS");

//==================================
//get columns
//==================================
$query = $dbh->prepare("
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

while($row = $query->fetch())
{
    $json[] = $row;
}

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
