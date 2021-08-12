<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class AppointmentForeignKeys
{
    public static function updatePatientTableLink(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `MediVisitAppointmentList`
            ADD CONSTRAINT `FK_MediVisitAppointmentList_Patient` FOREIGN KEY (`PatientSerNum`) REFERENCES `Patient` (`PatientSerNum`)
        ");
    }

    public static function updateResourceCodeLinks(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `MediVisitAppointmentList`
            ADD CONSTRAINT `FK_MediVisitAppointmentList_ClinicResources` FOREIGN KEY (`ClinicResourcesSerNum`) REFERENCES `ClinicResources` (`ClinicResourcesSerNum`),
            ADD CONSTRAINT `FK_MediVisitAppointmentList_AppointmentCode` FOREIGN KEY (`AppointmentCodeId`) REFERENCES `AppointmentCode` (`AppointmentCodeId`);
        ");

        //delete redundant column in the table
        $dbh->query("
            ALTER TABLE `MediVisitAppointmentList`
            DROP COLUMN `Resource`,
            DROP COLUMN `ResourceDescription`,
            DROP COLUMN `AppointmentCode`,
            DROP COLUMN `AppointIdIn`
        ");
    }
}
