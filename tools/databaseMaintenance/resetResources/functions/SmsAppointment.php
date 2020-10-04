<?php declare(strict_types = 1);

#modify sms appointment table to support appointment code + resource code combination
require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Config;

SmsAppointment::__init();

class SmsAppointment
{
    private static PDO $dbh;

    public static function __init(): void
    {
        self::$dbh = Config::getDatabaseConnection("ORMS");
    }

    static function createSmsFeatureTable(): void
    {
        self::$dbh->beginTransaction();

        self::_replaceSmsAppointmentTable();
        self:: _generateCrossList();
        self::_updateMessagesTable();
        self::_linkSmsTables();

        self::$dbh->commit();
    }

    private static function _replaceSmsAppointmentTable(): void
    {
        self::$dbh->query("

        ");
    }

    private static function _generateCrossList(): void
    {
        self::$dbh->query("

        ");
    }

    private static function _updateMessagesTable(): void
    {
        self::$dbh->query("

        ");
    }

    private static function _linkSmsTables(): void
    {
        self::$dbh->query("

        ");
    }
}

?>
