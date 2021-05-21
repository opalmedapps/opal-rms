<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

function addDateOfBirthColumn(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `Patient`
        ADD COLUMN `DateOfBirth` DATETIME NOT NULL AFTER `SSNExpDate`;
    ");
}
