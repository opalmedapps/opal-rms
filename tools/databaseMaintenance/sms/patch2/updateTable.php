<?php declare(strict_types = 1);

#insert diagnosis codes in database
require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Config;

patchSmsLog();

############################################
function patchSmsLog()
{
    $dbh = Database::getLogsConnection();
    $dbh->query("
        ALTER TABLE `SmsLog`
        CHANGE COLUMN `Result` `Result` TEXT NOT NULL DEFAULT '' COLLATE 'latin1_swedish_ci' AFTER `ProcessedTimestamp`;
    ");
}

?>
