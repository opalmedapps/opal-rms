<?php declare(strict_types=1);
//script to get a list of patient columns in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;

//get webpage parameters
$speciality = $_GET['speciality'];

//connect to db
$dbh = Database::getOrmsConnection();

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

$json = Encoding::utf8_encode_recursive($query->fetchAll());
echo json_encode($json);
