<?php declare(strict_types = 1);

//update appointment table so that the sourceId + sourceSystem is a unique key

require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Database;

$dbh = Database::getOrmsConnection();

addAppointmentCodeDescription($dbh);

function addAppointmentCodeDescription(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `AppointmentCode`
        ADD COLUMN `AppointmentCodeDescription` VARCHAR(100) NOT NULL AFTER `AppointmentCode`;
    ;");
}
