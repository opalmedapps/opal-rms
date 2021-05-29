<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

class ClinicHubs
{
    static function recreateClinicHubTable(PDO $dbh): void
    {
        $dbh->query("
            DROP TABLE `ClinicHub`;
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
            CREATE TEMPORARY TABLE IF NOT EXISTS ExamRoom_TEMP AS (SELECT * FROM ExamRoom_TEMP);
        ");

        $dbh->query("
            ALTER TABLE `ExamRoom`
            CHANGE COLUMN `Level` `ClinicHubId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AriaVenueId`,
            ADD CONSTRAINT `FK_ExamRoom_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`);
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
    }

    static function linkIntermediateVenueTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS IntermediateVenue_TEMP AS (SELECT * FROM IntermediateVenue_TEMP);
        ");

        $dbh->query("
            ALTER TABLE `IntermediateVenue`
            CHANGE COLUMN `Level` `ClinicHubId` INT NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AriaVenueId`,
            ADD CONSTRAINT `FK_IntermediateVenue_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`);
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
    }

    static function linkProfileTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TEMPORARY TABLE IF NOT EXISTS Profile_TEMP AS (SELECT * FROM Profile_TEMP);
        ");

        $dbh->query("
            ALTER TABLE `Profile`
            CHANGE COLUMN `ClinicalArea` `ClinicHubId` INT NULL COLLATE 'latin1_swedish_ci' AFTER `SpecialityGroup`,
            ADD CONSTRAINT `FK_Profile_ClinicHub` FOREIGN KEY (`ClinicHubId`) REFERENCES `ClinicHub` (`ClinicHubId`);
        ");

        $dbh->query("
            UPDATE Profile P
            INNER JOIN Profile_TEMP AS TMP ON TMP.ProfilerSer = P.ProfileSer
            LEFT JOIN ClinicHub CH ON CH.ClinicHubName = TMP.ClinicalArea
            SET
                P.ClinicHubId = CH.ClinicHubId
            WHERE
                1
        ");
    }
}
