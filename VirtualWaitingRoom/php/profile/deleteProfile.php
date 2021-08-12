<?php

declare(strict_types=1);
//script to delete a profile in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Util\Encoding;

//get webpage parameters
$profileId = Encoding::utf8_decode_recursive($_GET["profileId"]);

//connect to db
$dbh = Database::getOrmsConnection();

//call the delete stored procedure
$query = Database::getOrmsConnection()->prepare("CALL DeleteProfile(?);");
$query->execute([$profileId]);
