<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class KioskLog
{
    public static function createLogTable(PDO $dbh): void
    {
        $dbh->query("DROP TABLE IF EXISTS KioskLog");
        $dbh->query("
            CREATE TABLE KioskLog (
                KioskLogId INT(11) NOT NULL AUTO_INCREMENT,
                Timestamp DATETIME NOT NULL DEFAULT current_timestamp(),
                KioskInput VARCHAR(50) NULL DEFAULT NULL,
                KioskLocation VARCHAR(50) NOT NULL,
                PatientDestination VARCHAR(100) NULL DEFAULT NULL,
                ArrowDirection VARCHAR(50) NULL DEFAULT NULL,
                DisplayMessage TEXT NULL DEFAULT NULL,
                PRIMARY KEY (`KioskLogId`) USING BTREE
            )
        ");
    }
}
