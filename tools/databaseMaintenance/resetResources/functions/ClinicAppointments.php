<?php declare(strict_types = 1);

#extracts all appointment codes from the appointment table and inserts them into a new table
require_once __DIR__ ."/../../../../vendor/autoload.php";

class ClinicAppointments
{
    static function regenerateClinicApp()
    {
        self::_createAppointmentCodeTable();
        self::_updateAppointmentTable();
        self::_insertAppointmentCodes();
        self::_relinkAppointments();
    }

    private static function _createAppointmentCodeTable()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
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

        $dbh->commit();
    }

    private static function _updateAppointmentTable()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
            ALTER TABLE `MediVisitAppointmentList`
            ADD COLUMN `AppointmentCodeId` INT NOT NULL AFTER `AppointmentCode`;
        ");

        $dbh->commit();
    }

    private static function _insertAppointmentCodes()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $queryCodes = $dbh->prepare("
            SELECT DISTINCT
                MV.AppointmentCode
                ,CR.Speciality
            FROM MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum;
        ");
        $codes = $queryCodes->fetchAll();

        $insertCodes = $dbh->prepare("
            INSERT INTO ClinicResources(AppointmentCode,Speciality)
            VALUES(:code,:spec);
        ");
        foreach($codes as $code) {
            $insertCodes->execute([
                ":code" => $code["AppointmentCode"],
                ":spec" => $code["Speciality"],
            ]);
        }

        $dbh->commit();
    }

    private static function _relinkAppointments()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.AppointmentCodeSer = 0
            WHERE 1;
        ");

       $dbh->query("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN AppointmentCode AC ON AC.AppointmentCode = MV.AppointmentCode
                AND AC.Speciality = CR.Speciality
            SET
                MV.AppointmentCodeId = AC.AppointmentCodeId
            WHERE
                MV.AppointmentCodeSer = 0;
        ");

        $dbh->commit();
    }

}

?>
