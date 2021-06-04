<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

class ClinicHubs
{
    static function recreateClinicHubTable(PDO $dbh): void
    {
        $dbh->query("
            DROP TABLE IF EXISTS `ClinicHub`;
        ");

        $dbh->query("
            CREATE TABLE `ClinicHub` (
                `ClinicHubId` INT(11) NOT NULL AUTO_INCREMENT,
                `SpecialityGroupId` INT(11) NOT NULL,
                `ClinicHubName` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `LastUpdated` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`ClinicHubId`) USING BTREE,
                INDEX `FK_ClinicHub_SpecialityGroup` (`SpecialityGroupId`) USING BTREE,
                CONSTRAINT `FK_ClinicHub_SpecialityGroup` FOREIGN KEY (`SpecialityGroupId`) REFERENCES `OrmsDatabase`.`SpecialityGroup` (`SpecialityGroupId`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");

        $dbh->query("
            INSERT INTO ClinicHub(ClinicHubName,SpecialityGroupId)
            VALUES
                ('D RC',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'CCC')),
                ('D S1',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'CCC')),
                ('D 02',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'CCC')),
                ('Cardiovascular Clinics',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'MedClin')),
                ('CVIS - ID Clinics',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'MedClin')),
                ('Medical Clinics',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'MedClin')),
                ('Surgical Clinics - North',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'SurgClin')),
                ('Surgical Clinics - South',(SELECT SpecialityGroupId FROM SpecialityGroup WHERE SpecialityGroupCode = 'SurgClin'))
        ");
    }

    static function linkExamRoomTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS ExamRoom_TEMP AS (SELECT * FROM ExamRoom);
        ");

        $dbh->query("
            ALTER TABLE `ExamRoom`
            DROP COLUMN `Level`,
            ADD COLUMN `ClinicHubId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AriaVenueId`
        ");

        $dbh->query("
            UPDATE ExamRoom ER
            INNER JOIN ExamRoom_TEMP AS TMP ON TMP.AriaVenueId = ER.AriaVenueId
            INNER JOIN ClinicHub CH ON CH.ClinicHubName = TMP.Level
            SET
                ER.ClinicHubId = CH.ClinicHubId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `ExamRoom`
            ADD CONSTRAINT `FK_ExamRoom_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`)
        ");
    }

    static function linkIntermediateVenueTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS IntermediateVenue_TEMP AS (SELECT * FROM IntermediateVenue);
        ");

        $dbh->query("
            ALTER TABLE `IntermediateVenue`
            DROP COLUMN `Level`,
            ADD COLUMN `ClinicHubId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AriaVenueId`
        ");

        $dbh->query("
            UPDATE IntermediateVenue IV
            INNER JOIN IntermediateVenue_TEMP AS TMP ON TMP.IntermediateVenueSerNum = IV.IntermediateVenueSerNum
            INNER JOIN ClinicHub CH ON CH.ClinicHubName = TMP.Level
            SET
                IV.ClinicHubId = CH.ClinicHubId
            WHERE
                1
        ");

        $dbh->query("
            ALTER TABLE `IntermediateVenue`
            ADD CONSTRAINT `FK_IntermediateVenue_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`)
        ");
    }

    static function unlinkProfileTable(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `Profile`
            DROP COLUMN `ClinicalArea`
        ");
    }
}
