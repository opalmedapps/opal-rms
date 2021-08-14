<?php

declare(strict_types=1);

// script that logs information to the vwr logs in the database

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;

$now = new DateTime();

$params = Http::getRequestContents();

$filename   = $params["filename"];
$identifier = $params["identifier"];
$type       = $params["type"];
$message    = $params["message"];

//connect to database and log message
$dbh = Database::getLogsConnection();

$query = $dbh->prepare("
    INSERT INTO VirtualWaitingRoomLog (DateTime,FileName,Identifier,Type,Message)
    VALUES (:date,:file,:id,:type,:message)
");
$query->execute([
    ":date"     => $now->format("Y-m-d H:i:s"),
    ":file"     => $filename,
    ":id"       => $identifier,
    ":type"     => $type,
    ":message"  => $message,
]);

Http::generateResponseJsonAndExit(200);
