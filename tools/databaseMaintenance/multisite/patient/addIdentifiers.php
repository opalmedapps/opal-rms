<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

class PatientIdentifiers
{
    static function createHospitalTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `Hospital` (
                `HospitalId` INT(11) NOT NULL AUTO_INCREMENT,
                `HospitalCode` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `HospitalName` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `Format` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                PRIMARY KEY (`HospitalId`) USING BTREE,
                UNIQUE INDEX `HospitalCode` (`HospitalCode`) USING BTREE
            );
        ");

        $dbh->query("
            INSERT INTO `Hospital` (`HospitalCode`, `HospitalName`, `Format`)
            VALUES
                ('RVH', 'Royal Victoria Hospital', '^[0-9]{7}$');
                ('MCH', 'Montreal Children\'s Hospital', '^[0-9]{7}$');
        ");
    }

    static function createPatientHospitalIdentifierTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `PatientHospitalIdentifier` (
                `PatientHospitalIdentifierId` INT(11) NOT NULL AUTO_INCREMENT,
                `PatientId` INT(11) NOT NULL,
                `HospitalId` INT(11) NOT NULL,
                `MedicalRecordNumber` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `Active` TINYINT(4) NOT NULL,
                `DateAdded` DATETIME NOT NULL DEFAULT current_timestamp(),
                `LastModified` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`PatientHospitalIdentifierId`) USING BTREE,
                UNIQUE INDEX `HospitalId_MedicalRecordNumber` (`HospitalId`, `MedicalRecordNumber`) USING BTREE,
                INDEX `FK_PatientHospitalIdentifier_Patient` (`PatientId`) USING BTREE,
                CONSTRAINT `FK_PatientHospitalIdentifier_Hospital` FOREIGN KEY (`HospitalId`) REFERENCES `OrmsDatabase`.`Hospital` (`HospitalId`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT `FK_PatientHospitalIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `OrmsDatabase`.`Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");
    }

    static function createInsuranceTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `Insurance` (
                `InsuranceId` INT(11) NOT NULL AUTO_INCREMENT,
                `InsuranceCode` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `InsuranceName` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `Format` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                PRIMARY KEY (`InsuranceId`) USING BTREE,
                UNIQUE INDEX `InsuranceCode` (`InsuranceCode`) USING BTREE
            )
        ");

        $dbh->query("
            INSERT INTO `Insurance` (`InsuranceCode`, `InsuranceName`, `Format`)
            VALUES
                ('RAMQ', 'Régie de l\'assurance maladie du Québec', '^[a-zA-Z]{4}[0-9]{8}$');
        ");
    }

    static function createPatientInsuranceIdentifierTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `PatientInsuranceIdentifier` (
                `PatientInsuranceIdentifierId` INT(11) NOT NULL AUTO_INCREMENT,
                `PatientId` INT(11) NOT NULL,
                `InsuranceId` INT(11) NOT NULL,
                `InsuranceNumber` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `ExpirationDate` DATETIME NOT NULL,
                `Active` TINYINT(4) NOT NULL,
                `DateAdded` DATETIME NOT NULL DEFAULT current_timestamp(),
                `LastModified` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`PatientInsuranceIdentifierId`) USING BTREE,
                UNIQUE INDEX `InsuranceId_InsuranceNumber` (`InsuranceId`, `InsuranceNumber`) USING BTREE,
                INDEX `FK_PatientInsuranceIdentifier_Patient` (`PatientId`) USING BTREE,
                CONSTRAINT `FK_PatientInsuranceIdentifier_Insurance` FOREIGN KEY (`InsuranceId`) REFERENCES `OrmsDatabase`.`Insurance` (`InsuranceId`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT `FK_PatientInsuranceIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `OrmsDatabase`.`Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");
    }
}
