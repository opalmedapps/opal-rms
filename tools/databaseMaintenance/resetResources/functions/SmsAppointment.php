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

        self::_updateSmsAppointmentTable();
        self:: _generateCrossList();

        self::$dbh->commit();
    }

    private static function _updateSmsAppointmentTable(): void
    {
        self::$dbh->query("
            DROP TABLE SmsAppointment;
        ");

        self::$dbh->query("
            CREATE TABLE `SmsAppointment` (
                `SmsAppointmentId` INT NOT NULL AUTO_INCREMENT,
                `ClinicResourcesSerNum` INT NOT NULL,
                `AppointmentCodeId` INT NOT NULL,
                `Speciality` VARCHAR(50) NOT NULL,
                `Type` VARCHAR(50) NULL DEFAULT NULL,
                `Active` TINYINT(4) NOT NULL DEFAULT 0,
                `LastUpdated` DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW(),
                PRIMARY KEY (`SmsAppointmentId`),
                UNIQUE INDEX `ClinicResourcesSerNum` (`ClinicResourcesSerNum`, `AppointmentCodeId`) USING BTREE,
                CONSTRAINT `FK__ClinicResources` FOREIGN KEY (`ClinicResourcesSerNum`) REFERENCES `ClinicResources` (`ClinicResourcesSerNum`),
                CONSTRAINT `FK__AppointmentCode` FOREIGN KEY (`AppointmentCodeId`) REFERENCES `AppointmentCode` (`AppointmentCodeId`),
                CONSTRAINT `FK__Type` FOREIGN KEY (`Type`) REFERENCES `SmsMessage` (`Type`)
            );
        ");
    }

    private static function _generateCrossList(): void
    {
        $queryCodes = self::$dbh->query("
            SELECT DISTINCT
                CR.ClinicResourcesSerNum
                ,AC.AppointmentCodeId
                ,CR.Speciality
            FROM
                MediVisitAppointmentList MV
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
            ORDER BY CR.ClinicResourcesSerNum, AC.AppointmentCodeId
        ");
        $codes = $queryCodes->fetchAll() ?: [];

        $insertCodes = self::$dbh->prepare("
            INSERT INTO SmsAppointment(ClinicResourcesSerNum,AppointmentCodeId,Speciality)
            VALUES(:res,:app,:spec);
        ");
        foreach($codes as $code) {
            $insertCodes->execute([
                ":res" => $code["ClinicResourcesSerNum"],
                ":app" => $code["AppointmentCodeId"],
                ":spec" => $code["Speciality"],
            ]);
        }
    }
}

?>
