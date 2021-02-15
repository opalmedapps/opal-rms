<?php declare(strict_types = 1);

#modify sms appointment table to support appointment code + resource code combination
require_once __DIR__ ."/../../../vendor/autoload.php";

use Orms\Database;

$dbh = Database::getOrmsConnection();
$query = $dbh->exec("
    ALTER TABLE `Profile`
    DROP COLUMN `FetchResourcesFromVenues`,
    DROP COLUMN `FetchResourcesFromClinics`,
    DROP COLUMN `ShowCheckedOutAppointments`;
");

?>
