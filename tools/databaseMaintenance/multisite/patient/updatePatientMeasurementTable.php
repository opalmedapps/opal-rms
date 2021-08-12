<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

class PatientMeasurementTable
{
    public static function linkPatientMeasurementTable(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `PatientMeasurement`
            DROP INDEX `ID_PatientSer`,
            ADD CONSTRAINT `FK_PatientMeasurement_Patient` FOREIGN KEY (`PatientSer`) REFERENCES `Patient` (`PatientSerNum`) ON UPDATE RESTRICT ON DELETE RESTRICT;
        ");
    }

    public static function updatePatientIdColumn(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `PatientMeasurement`
            CHANGE COLUMN `PatientId` `PatientId` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointmentId`;
        ");

        $dbh->query("
            UPDATE PatientMeasurement
            SET
                PatientId = CONCAT(PatientId,'-RVH')
            WHERE
                PatientId IS NOT NULL
        ");
    }
}
