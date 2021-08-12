<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class PatientIdentifiers
{
    public static function createHospitalTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `Hospital` (
                `HospitalId` INT(11) NOT NULL AUTO_INCREMENT,
                `HospitalCode` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `HospitalName` VARCHAR(100) NOT NULL COLLATE 'latin1_swedish_ci',
                `Format` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                PRIMARY KEY (`HospitalId`) USING BTREE,
                UNIQUE INDEX `HospitalCode` (`HospitalCode`) USING BTREE
            );
        ");

        $dbh->query("
            INSERT INTO `Hospital` (`HospitalCode`, `HospitalName`, `Format`)
            VALUES
                ('RVH', 'Royal Victoria Hospital', '^[0-9]{7}$'),
                ('MCH', 'Montreal Children\'s Hospital', '^[0-9]{7}$'),
                ('MGH', 'Montreal General Hospital', '^[0-9]{7}$'),
                ('LAC', 'Lachine Hospital', '^[0-9]{7}$'),
                ('CRE', 'Cree Board of Health and Social Services of James Bay', '^[0-9]{7}$')
        ");
    }

    public static function createPatientHospitalIdentifierTable(PDO $dbh): void
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
                CONSTRAINT `FK_PatientHospitalIdentifier_Hospital` FOREIGN KEY (`HospitalId`) REFERENCES `Hospital` (`HospitalId`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT `FK_PatientHospitalIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");
    }

    public static function createInsuranceTable(PDO $dbh): void
    {
        $dbh->query("
            CREATE TABLE `Insurance` (
                `InsuranceId` INT(11) NOT NULL AUTO_INCREMENT,
                `InsuranceCode` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
                `InsuranceName` VARCHAR(100) NOT NULL COLLATE 'latin1_swedish_ci',
                `Format` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                PRIMARY KEY (`InsuranceId`) USING BTREE,
                UNIQUE INDEX `InsuranceCode` (`InsuranceCode`) USING BTREE
            )
        ");

        $dbh->query("SET NAMES utf8mb4;");
        $dbh->query("
            INSERT INTO `Insurance` (`InsuranceCode`, `InsuranceName`, `Format`)
            VALUES
                ('RAMA','Régie de l\'assurance maladie de l\'Alberta','^[0-9]{9}$'),
                ('RAMC','Régie de l\'assurance maladie de la Colombie-Britannique','^[0-9]{10}$'),
                ('RAMM','Régie de l\'assurance maladie du Manitoba','^[0-9]{9}$'),
                ('RAMB','Régie de l\'assurance maladie du Nouveau-Brunswick','^[0-9]{9}$'),
                ('RAMN','Régie de l\'assurance maladie de Terre-Neuve','^[0-9]{12}$'),
                ('RAMT','Régie de l\'assurance maladie des Territoires NO','^[0-9]{7}$'),
                ('RAME','Régie de l\'assurance maladie de la Nouvelle-Ecosse','^[0-9]{10}$'),
                ('RAMU','Régie de l\'assurance maladie du Nunavut','^[0-9]{9}$'),
                ('RAMO','Régie de l\'assurance maladie de l\'Ontario','^([0-9]{10}[A-Z]{2}|[0-9]{10})$'),
                ('RAMI','Régie de l\'assurance maladie de l\'IPE','^[0-9]{8}$'),
                ('RAMQ','Régie de l\'assurance maladie du Québec','^[a-zA-Z]{4}[0-9]{8}$'),
                ('RAMS','Régie de l\'assurance maladie de la Saskatchewan','^[0-9]{9}$'),
                ('RAMY','Régie de l\'assurance maladie du Yukon','^[0-9]{9}$')
        ");
        $dbh->query("SET NAMES latin1;");
    }

    public static function createPatientInsuranceIdentifierTable(PDO $dbh): void
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
                CONSTRAINT `FK_PatientInsuranceIdentifier_Insurance` FOREIGN KEY (`InsuranceId`) REFERENCES `Insurance` (`InsuranceId`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT `FK_PatientInsuranceIdentifier_Patient` FOREIGN KEY (`PatientId`) REFERENCES `Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT
            )
        ");
    }
}
