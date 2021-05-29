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
            CHANGE COLUMN `Speciality` `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointmentCode`,
            DROP INDEX `AppointmentCode`,
            ADD UNIQUE INDEX `AppointmentCode_SpecialityGroupId` (`AppointmentCode`, `SpecialityGroupId`),
            ADD CONSTRAINT `FK_AppointmentCode_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`);
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
    }

    static function linkClinicResourcesTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS ClinicResources_TEMP AS (SELECT * FROM ClinicResources);
        ");

        $dbh->query("
            ALTER TABLE `ClinicResources`
            CHANGE COLUMN `Speciality` `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `ResourceName`,
            ADD UNIQUE INDEX `ClinicResources_SpecialityGroupId` (`ResourceCode`, `SpecialityGroupId`),
            ADD CONSTRAINT `FK_ClinicResources_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`);
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
    }

    static function linkProfileTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS Profile_TEMP AS (SELECT * FROM Profile);
        ");

        $dbh->query("
            ALTER TABLE `Profile`
            CHANGE COLUMN `Speciality` `SpecialityGroupId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `Category`,
            ADD CONSTRAINT `FK_Profile_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `SpecialityGroup` (`SpecialityGroupId`);
        ");

        $dbh->query("
            UPDATE Profile P
            INNER JOIN Profile_TEMP AS TMP ON TMP.ProfileSer = CR.ProfileSer
            INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupName = TMP.Speciality
            SET
                P.SpecialityGroupId = SG.SpecialityGroupId
            WHERE
                1
        ");
    }
}
