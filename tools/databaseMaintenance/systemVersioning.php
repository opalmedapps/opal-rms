<?php declare(strict_types = 1);

require_once __DIR__ ."/../../vendor/autoload.php";

use Orms\DataAccess\Database;

$dbh = Database::getOrmsConnection();

$tableQuery = $dbh->prepare("
    SELECT
        TABLE_NAME
    FROM
        information_schema.tables
    WHERE
        TABLE_SCHEMA = DATABASE();
");
$tableQuery->execute();

$tables = array_map(fn($x) => $x["TABLE_NAME"],$tableQuery->fetchAll());

foreach($tables as $table)
{
    echo "Altering table $table\n";
    $dbh->query("ALTER TABLE $table ADD SYSTEM VERSIONING");
}
