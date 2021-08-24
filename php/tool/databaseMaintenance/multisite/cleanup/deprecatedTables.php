<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class DeprecatedTables
{
    public static function removeDoctorSchedule(PDO $dbh): void
    {
        $dbh->query("DROP PROCEDURE IF EXISTS createDoctorSchedule");
        $dbh->query("DROP PROCEDURE IF EXISTS Create_Appointment");
        $dbh->query("DROP PROCEDURE IF EXISTS getDoctorsSchedule");
        $dbh->query("DROP EVENT IF EXISTS eventDoctorSchedule");
    }

    public static function removeVenue(PDO $dbh): void
    {
        $dbh->query("DROP TABLE IF EXISTS Venue");
    }

    public static function removeCheckoutEvent(PDO $dbh): void
    {
        $dbh->query("DROP EVENT IF EXISTS EndOfDayCheckout");
    }
}
