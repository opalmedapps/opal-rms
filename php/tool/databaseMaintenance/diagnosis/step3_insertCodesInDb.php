<?php

declare(strict_types=1);

//insert diagnosis codes in database
require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Config;
use Orms\DataAccess\Database;

$data = json_decode(file_get_contents(__DIR__."/data/processed_codes.json") ?: "", true);

$dbh = Database::getOrmsConnection();
$dbh->query("SET FOREIGN_KEY_CHECKS = 0;");

createDiagnosisChapterTable($dbh);
createDiagnosisCodeTable($dbh);
createDiagnosisSubcodeTable($dbh);
createPatientDiagnosisTable($dbh);

$dbh->query("SET FOREIGN_KEY_CHECKS = 1;");

insertChapters($dbh, $data["chapters"]);
insertCodes($dbh, $data["codes"]);
insertSubcodes($dbh, $data["subcodes"]);

addDiagnosisColumnToVwr($dbh);

//###########################################
function createDiagnosisChapterTable(PDO $dbh): void
{
    $dbh->query("DROP TABLE IF EXISTS DiagnosisChapter;");
    $dbh->query("
        CREATE TABLE `DiagnosisChapter` (
            `DiagnosisChapterId` INT(11) NOT NULL AUTO_INCREMENT,
            `Chapter` VARCHAR(20) NOT NULL COLLATE 'latin1_swedish_ci',
            `Description` TEXT NOT NULL COLLATE 'latin1_swedish_ci',
            PRIMARY KEY (`DiagnosisChapterId`) USING BTREE,
            UNIQUE INDEX `Chapter` (`Chapter`) USING BTREE
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

function createDiagnosisCodeTable(PDO $dbh): void
{
    $dbh->query("DROP TABLE IF EXISTS DiagnosisCode;");
    $dbh->query("
        CREATE TABLE `DiagnosisCode` (
            `DiagnosisCodeId` INT(11) NOT NULL AUTO_INCREMENT,
            `DiagnosisChapterId` INT(11) NOT NULL,
            `Code` VARCHAR(20) NOT NULL COLLATE 'latin1_swedish_ci',
            `Category` TEXT NOT NULL COLLATE 'latin1_swedish_ci',
            `Description` TEXT NOT NULL COLLATE 'latin1_swedish_ci',
            PRIMARY KEY (`DiagnosisCodeId`) USING BTREE,
            UNIQUE INDEX `Code` (`Code`) USING BTREE,
            INDEX `FK_DiagnosisCode_DiagnosisChapter` (`DiagnosisChapterId`) USING BTREE,
            CONSTRAINT `FK_DiagnosisCode_DiagnosisChapter` FOREIGN KEY (`DiagnosisChapterId`) REFERENCES `DiagnosisChapter` (`DiagnosisChapterId`) ON UPDATE RESTRICT ON DELETE RESTRICT
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

function createDiagnosisSubcodeTable(PDO $dbh): void
{
    $dbh->query("DROP TABLE IF EXISTS DiagnosisSubcode;");
    $dbh->query("
        CREATE TABLE `DiagnosisSubcode` (
            `DiagnosisSubcodeId` INT(11) NOT NULL AUTO_INCREMENT,
            `DiagnosisCodeId` INT(11) NOT NULL,
            `Subcode` VARCHAR(20) NOT NULL COLLATE 'latin1_swedish_ci',
            `Description` TEXT NOT NULL COLLATE 'latin1_swedish_ci',
            PRIMARY KEY (`DiagnosisSubcodeId`) USING BTREE,
            UNIQUE INDEX `Subcode` (`Subcode`) USING BTREE,
            INDEX `FK_DiagnosisSubcode_DiagnosisCode` (`DiagnosisCodeId`) USING BTREE,
            CONSTRAINT `FK_DiagnosisSubcode_DiagnosisCode` FOREIGN KEY (`DiagnosisCodeId`) REFERENCES `DiagnosisCode` (`DiagnosisCodeId`) ON UPDATE RESTRICT ON DELETE RESTRICT
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

function createPatientDiagnosisTable(PDO $dbh): void
{
    $dbh->query("DROP TABLE IF EXISTS PatientDiagnosis;");
    $dbh->query("
        CREATE TABLE `PatientDiagnosis` (
            `PatientDiagnosisId` INT(11) NOT NULL AUTO_INCREMENT,
            `PatientSerNum` INT(11) NOT NULL,
            `DiagnosisSubcodeId` INT(11) NOT NULL,
            `Status` ENUM('Active','Deleted') NOT NULL DEFAULT 'Active' COLLATE 'latin1_swedish_ci',
            `DiagnosisDate` DATETIME NOT NULL DEFAULT current_timestamp(),
            `CreatedDate` DATETIME NOT NULL DEFAULT current_timestamp(),
            `LastUpdated` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            `UpdatedBy` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci',
            PRIMARY KEY (`PatientDiagnosisId`) USING BTREE,
            INDEX `FK__Patient` (`PatientSerNum`) USING BTREE,
            INDEX `FK_PatientDiagnosis_DiagnosisSubcode` (`DiagnosisSubcodeId`) USING BTREE,
            CONSTRAINT `FK_PatientDiagnosis_DiagnosisSubcode` FOREIGN KEY (`DiagnosisSubcodeId`) REFERENCES `DiagnosisSubcode` (`DiagnosisSubcodeId`) ON UPDATE RESTRICT ON DELETE RESTRICT,
            CONSTRAINT `FK__Patient` FOREIGN KEY (`PatientSerNum`) REFERENCES `Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;
    ");
}

/** @param mixed[] $chapters */
function insertChapters(PDO $dbh, array $chapters): void
{
    $query = $dbh->prepare("
        INSERT INTO DiagnosisChapter(Chapter,Description)
        VALUES(:chapter,:description);
    ");
    foreach($chapters as $x)
    {
        $query->execute([
            ":chapter"      => $x["chapter"],
            ":description"  => $x["description"]
        ]);
    }
}

/** @param mixed[] $codes */
function insertCodes(PDO $dbh, array $codes): void
{
    $query = $dbh->prepare("
        INSERT INTO DiagnosisCode(Code,DiagnosisChapterId,Category,Description)
        VALUES(
            :code
            ,(SELECT DiagnosisChapter.DiagnosisChapterId FROM DiagnosisChapter WHERE DiagnosisChapter.Chapter = :chapter)
            ,:category
            ,:description
        );
    ");
    foreach($codes as $x)
    {
        $query->execute([
            ":code"         => $x["code"],
            ":chapter"      => $x["chapter"],
            ":category"     => $x["category"],
            ":description"  => $x["description"]
        ]);
    }
}

/** @param mixed[] $subcodes */
function insertSubcodes(PDO $dbh, array $subcodes): void
{
    $query = $dbh->prepare("
        INSERT INTO DiagnosisSubcode(Subcode,DiagnosisCodeId,Description)
        VALUES(
            :subcode
            ,(SELECT DiagnosisCode.DiagnosisCodeId FROM DiagnosisCode WHERE DiagnosisCode.Code = :code)
            ,:description
        );
    ");
    foreach($subcodes as $x)
    {
        $query->execute([
            ":subcode"      => $x["subcode"],
            ":code"         => $x["code"],
            ":description"  => $x["description"]
        ]);
    }
}

function addDiagnosisColumnToVwr(PDO $dbh): void
{
    $dbh->prepare("
        INSERT INTO ProfileColumnDefinition(ColumnName,DisplayName,Glyphicon,Description,Speciality)
        VALUES('Diagnosis','Diagnosis','glyphicon-tint','Patient Diagnosis','All')
        ON DUPLICATE KEY UPDATE
            ColumnName     = VALUES(ColumnName),
            DisplayName    = VALUES(DisplayName),
            Glyphicon      = VALUES(Glyphicon),
            Description    = VALUES(Description),
            Speciality     = VALUES(Speciality)
    ")->execute();

    $scriptPath = Config::getApplicationSettings()->environment->basePath ."/php/tool/verifyProfileColumns.php";
    /** @psalm-suppress ForbiddenCode */
    shell_exec("php $scriptPath");
}
