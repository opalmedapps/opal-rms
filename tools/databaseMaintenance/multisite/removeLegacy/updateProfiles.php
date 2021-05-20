<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

function removeLegacyProfileColumns(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `Profile`
        DROP COLUMN `FetchResourcesFromVenues`,
        DROP COLUMN `FetchResourcesFromClinics`,
        DROP COLUMN `ShowCheckedOutAppointments`;
    ;");
}
