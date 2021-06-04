<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

class SpecialityGroup
{
    static function createSpecialityGroupTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `SpecialityGroup` (
                `SpecialityGroupId` INT(11) NOT NULL AUTO_INCREMENT,
                `HospitalId` INT(11) NOT NULL,
                `SpecialityGroupCode` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `SpecialityGroupName` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `LastUpdated` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`SpecialityGroupId`) USING BTREE,
                INDEX `FK_SpecialityGroup_Hospital` (`HospitalId`) USING BTREE,
                UNIQUE INDEX `SpecialityGroupCode` (`SpecialityGroupCode`),
                CONSTRAINT `FK_SpecialityGroup_Hospital` FOREIGN KEY (`HospitalId`) REFERENCES `OrmsDatabase`.`Hospital` (`HospitalId`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");

        $dbh->query("
            INSERT INTO SpecialityGroup(SpecialityGroupCode,SpecialityGroupName,HospitalId)
            VALUES
            ('CCC','Cedars Cancer Centre',(SELECT HospitalId FROM Hospital WHERE HospitalCode = 'RVH')),
            ('MedClin','Medicine Clinics - RVH',(SELECT HospitalId FROM Hospital WHERE HospitalCode = 'RVH')),
            ('SurgClin','Surgical Clinics - RVH',(SELECT HospitalId FROM Hospital WHERE HospitalCode = 'RVH'))
        ");
    }

    static function linkAppointmentCodeTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS AppointmentCode_TEMP AS (SELECT * FROM AppointmentCode);
        ");

        $dbh->query("
            ALTER TABLE `AppointmentCode`
            DROP COLUMN `Speciality`,
            ADD COLUMN `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointmentCode`,
            DROP INDEX `AppointmentCode`,
            ADD UNIQUE INDEX `AppointmentCode_SpecialityGroupId` (`AppointmentCode`, `SpecialityGroupId`)
        ");

        $dbh->query("
            UPDATE AppointmentCode AC
            INNER JOIN AppointmentCode_TEMP AS TMP ON TMP.AppointmentCodeId = AC.AppointmentCodeId
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                AC.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `AppointmentCode`
            ADD CONSTRAINT `FK_AppointmentCode_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
        ");
    }

    static function linkClinicResourcesTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS ClinicResources_TEMP AS (SELECT * FROM ClinicResources);
        ");

        $dbh->query("
            ALTER TABLE `ClinicResources`
            DROP COLUMN `Speciality`,
            ADD COLUMN `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `ResourceName`,
            ADD UNIQUE INDEX `ClinicResources_SpecialityGroupId` (`ResourceCode`, `SpecialityGroupId`)
        ");

        $dbh->query("
            UPDATE ClinicResources CR
            INNER JOIN ClinicResources_TEMP AS TMP ON TMP.ClinicResourcesSerNum = CR.ClinicResourcesSerNum
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                CR.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `ClinicResources`
            ADD CONSTRAINT `FK_ClinicResources_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
        ");
    }

    static function linkProfileTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS Profile_TEMP AS (SELECT * FROM Profile);
        ");

        $dbh->query("
            ALTER TABLE `Profile`
            DROP COLUMN `Speciality`,
            ADD COLUMN `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `Category`
        ");

        $dbh->query("
            UPDATE Profile P
            INNER JOIN Profile_TEMP AS TMP ON TMP.ProfileSer = P.ProfileSer
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                P.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `Profile`
            ADD CONSTRAINT `FK_Profile_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
        ");
    }

    static function linkSmsAppointmentTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS SmsAppointment_TEMP AS (SELECT * FROM SmsAppointment);
        ");

        $dbh->query("
            ALTER TABLE `SmsAppointment`
            DROP COLUMN `Speciality`,
            ADD COLUMN `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointmentCodeId`
        ");

        $dbh->query("
            UPDATE SmsAppointment SA
            INNER JOIN SmsAppointment_TEMP AS TMP ON TMP.SmsAppointmentId = SA.SmsAppointmentId
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                SA.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `SmsAppointment`
            ADD CONSTRAINT `FK_SmsAppointment_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`)
        ");
    }

    static function linkSmsMessageTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS SmsMessage_TEMP AS (SELECT * FROM SmsMessage);
        ");

        $dbh->query("
            ALTER TABLE `SmsMessage`
            ADD COLUMN `SpecialityGroupId` INT NULL AFTER `SmsMessageId`,
            DROP COLUMN `Speciality`,
            DROP INDEX `Speciality`,
            ADD UNIQUE INDEX `SpecialityGroupId_Type_Event_Language` (`SpecialityGroupId`, `Type`, `Event`, `Language`),
            ADD CONSTRAINT `FK_SmsMessage_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`);
        ");

        $dbh->query("
            UPDATE SmsMessage SM
            INNER JOIN SmsMessage_TEMP AS TMP ON TMP.SmsMessageId = SM.SmsMessageId
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                SM.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");
    }
}
