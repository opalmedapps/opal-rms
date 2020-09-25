<?php declare(strict_types = 1);

#insert diagnosis codes in database
require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Config;

createLastRunTable();
fillLastRunTable();
createSmsLogTable();

############################################
function createLastRunTable()
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $dbh->query("
        CREATE TABLE `Cron` (
            `System` VARCHAR(20) NOT NULL COLLATE 'latin1_swedish_ci',
            `LastReceivedSmsFetch` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`System`) USING BTREE
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

function fillLastRunTable()
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        INSERT INTO Cron(System,LastReceivedSmsFetch)
        VALUES('ORMS',NOW());
    ");
    $query->execute();
}

function createSmsLogTable()
{
    $dbh = Config::getDatabaseConnection("LOGS");
    $dbh->query("
        CREATE TABLE `SmsLog` (
            `SmsLogSer` INT(11) NOT NULL AUTO_INCREMENT,
            `SmsTimestamp` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            `ProcessedTimestamp` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            `Result` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            `Action` ENUM('SENT','RECEIVED') NOT NULL COLLATE 'latin1_swedish_ci',
            `Service` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            `MessageId` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            `ServicePhoneNumber` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            `ClientPhoneNumber` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            `Message` TEXT NOT NULL COLLATE 'latin1_swedish_ci',
            PRIMARY KEY (`SmsLogSer`) USING BTREE,
            UNIQUE INDEX `Service` (`Service`, `MessageId`) USING BTREE,
            INDEX `SmsTimestamp` (`SmsTimestamp`) USING BTREE,
            INDEX `ProcessedTimestamp` (`ProcessedTimestamp`) USING BTREE
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

?>
