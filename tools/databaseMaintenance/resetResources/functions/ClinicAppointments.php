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
                `LastModified` DATETIME NOT NULL DEFAULT NOW() ON UPDATE NOW(),
                `Active` TINYINT NOT NULL DEFAULT 1,
                PRIMARY KEY (`AppointmentCodeId`),
                UNIQUE INDEX `AppointmentCode` (`AppointmentCode`, `Speciality`)
            )
            COLLATE='latin1_swedish_ci'
            ;
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
        $queryCodes = self::$dbh->prepare("
            SELECT DISTINCT
                MV.AppointmentCode
                ,CR.Speciality
            FROM MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum;
        ");
        $codes = $queryCodes->fetchAll() ?: [];

        $insertCodes = self::$dbh->prepare("
            INSERT INTO ClinicResources(AppointmentCode,Speciality)
            VALUES(:code,:spec);
        ");
        foreach($codes as $code) {
            $insertCodes->execute([
                ":code" => $code["AppointmentCode"],
                ":spec" => $code["Speciality"],
            ]);
        }
    }

    private static function _relinkAppointments(): void
    {
        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.AppointmentCodeSer = 0
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
                MV.AppointmentCodeSer = 0;
        ");
    }

}

?>
