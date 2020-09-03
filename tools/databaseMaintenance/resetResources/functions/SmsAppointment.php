<?php declare(strict_types = 1);

#modify sms appointment table to support appointment code + resource code combination
require_once __DIR__ ."/../../../../vendor/autoload.php";

class SmsAppointment
{
    static function createSmsFeatureTable()
    {
        self::_replaceSmsAppointmentTable();
        self:: _generateCrossList();
        self::_updateMessagesTable();
        self::_linkSmsTables();
    }

    private static function _replaceSmsAppointmentTable()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("

        ");

        $dbh->commit();
    }

    private static function _generateCrossList()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("

        ");

        $dbh->commit();
    }

    private static function _updateMessagesTable()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("

        ");

        $dbh->commit();
    }

    private static function _linkSmsTables()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("

        ");

        $dbh->commit();
    }
}

?>
