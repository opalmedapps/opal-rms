<?php declare(strict_types = 1);

//update appointment table so that the sourceId + sourceSystem is a unique key

require_once __DIR__ ."/../../../../vendor/autoload.php";

function createSourceSystemKey(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `MediVisitAppointmentList`
        DROP INDEX `MedivisitAppointId`,
        ADD UNIQUE INDEX `MedivisitAppointId` (`AppointId`, `AppointSys`) USING BTREE
    ;");
}

function removeSourceSystemConstrain(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `MediVisitAppointmentList`
        CHANGE COLUMN `AppointSys` `AppointSys` VARCHAR(50) NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointIdIn`;
    ;");
}

function removeAriaPrefixFromSourceId(PDO $dbh): void
{
    //update appointment list
    $dbh->query("
        UPDATE MediVisitAppointmentList
        SET
            AppointId = REPLACE(AppointId,'Aria',''),
            AppointIdIn = REPLACE(AppointId,'Aria','')
        WHERE
            AppointId LIKE 'Aria%'
    ");

    //update patient measurements
    //update Aria appointments, then Medivisit ones
    $dbh->query("
        UPDATE PatientMeasurement
        SET
            AppointmentId = CONCAT('Aria','-',REPLACE(AppointmentId,'MEDIAria',''))
        WHERE
            AppointmentId LIKE 'MEDIAria%'
    ");

    $dbh->query("
        UPDATE PatientMeasurement
        SET
            AppointmentId = CONCAT('Aria','-',REPLACE(AppointmentId,'ARIA',''))
        WHERE
            AppointmentId LIKE 'ARIA%'
    ");

    $dbh->query("
        UPDATE PatientMeasurement
        SET
            AppointmentId = CONCAT('Medivisit','-',REPLACE(AppointmentId,'MEDI',''))
        WHERE
            AppointmentId LIKE 'MEDI%'
    ");
}
