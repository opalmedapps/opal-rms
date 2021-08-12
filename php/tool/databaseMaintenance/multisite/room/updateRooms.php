<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class Rooms
{
    public static function extendRoomNameLength(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `IntermediateVenue`
            CHANGE COLUMN `VenueEN` `VenueEN` VARCHAR(100) NOT NULL COLLATE 'latin1_swedish_ci' AFTER `ScreenDisplayName`,
            CHANGE COLUMN `VenueFR` `VenueFR` VARCHAR(100) NOT NULL COLLATE 'latin1_swedish_ci' AFTER `VenueEN`;
        ");

    }
}
