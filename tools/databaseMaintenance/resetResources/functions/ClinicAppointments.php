<?php declare(strict_types = 1);

#extracts all appointment codes from the appointment table and inserts them into a new table
require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Config;

ClinicAppointments::__init();

class ClinicAppointments
{
    private static PDO $dbh;

    public static function __init(): void
    {
        self::$dbh = Config::getDatabaseConnection("ORMS");
    }

    static function regenerateClinicApp(): void
    {
        self::$dbh->beginTransaction();

        self::_createAppointmentCodeTable();
        self::_updateAppointmentTable();
        self::_insertAppointmentCodes();
        self::_relinkAppointments();

        self::$dbh->commit();
    }

    private static function _createAppointmentCodeTable(): void
    {
        self::$dbh->query("
            CREATE TABLE `AppointmentCode` (
                `AppointmentCodeId` INT NOT NULL AUTO_INCREMENT,
                `AppointmentCode` VARCHAR(50) NOT NULL,
                `Speciality` VARCHAR(50) NOT NULL,
                `SourceSystem` VARCHAR(50) NOT NULL,
                `Active` TINYINT NOT NULL DEFAULT 1,
                `LastModified` DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW(),
                PRIMARY KEY (`AppointmentCodeId`),
                UNIQUE INDEX `AppointmentCode` (`AppointmentCode`, `Speciality`)
            );
        ");
    }

    private static function _updateAppointmentTable(): void
    {
        self::$dbh->query("
            ALTER TABLE `MediVisitAppointmentList`
            ADD COLUMN `AppointmentCodeId` INT NOT NULL AFTER `AppointmentCode`;
        ");
    }

    private static function _insertAppointmentCodes(): void
    {
        $queryCodes = self::$dbh->query("
            SELECT DISTINCT
                MV.AppointmentCode
                ,MV.AppointSys
                ,CR.Speciality
            FROM
                MediVisitAppointmentList MV
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            WHERE
                MV.AppointSys != 'InstantAddOn'
            ORDER BY
                MV.AppointSys
                ,MV.AppointmentCode
        ");
        $codes = $queryCodes->fetchAll() ?: [];

        $insertCodes = self::$dbh->prepare("
            INSERT INTO AppointmentCode(AppointmentCode,Speciality,SourceSystem)
            VALUES(:code,:spec,:sys);
        ");
        foreach($codes as $code) {
            $insertCodes->execute([
                ":code" => $code["AppointmentCode"],
                ":spec" => $code["Speciality"],
                ":sys"  => $code["AppointSys"]
            ]);
        }
    }

    private static function _relinkAppointments(): void
    {
        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.AppointmentCodeId = 0
            WHERE 1;
        ");

       self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN AppointmentCode AC ON AC.AppointmentCode = MV.AppointmentCode
                AND AC.Speciality = CR.Speciality
            SET
                MV.AppointmentCodeId = AC.AppointmentCodeId
            WHERE
                MV.AppointmentCodeId = 0;
        ");
    }

}

?>
