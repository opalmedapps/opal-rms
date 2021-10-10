<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class AppointmentStatus
{
    public static function removeInProgressStatus(PDO $dbh): void
    {
        $dbh->query("
            UPDATE MediVisitAppointmentList
            SET
                Status = 'Open'
            WHERE
                Status = 'In progress'
        ");

        $dbh->query("
            ALTER TABLE MediVisitAppointmentList
            CHANGE COLUMN Status Status ENUM('Open','Cancelled','Completed','Deleted') NOT NULL AFTER AppointSys;
        ");
    }

    public static function removeDeprecatedSourceStatuses(PDO $dbh): void
    {
        //remove all aria statuses
        $dbh->query("
            UPDATE MediVisitAppointmentList
            SET
                MedivisitStatus = NULL
            WHERE
                MedivisitStatus IN (
                    'Manually Completed',
                    'Deleted',
                    'Cancelled',
                    'Cancelled - Patient No-Show',
                    'Open',
                    'In Progress (Manually Set);',
                    'Completed',
                    'In Progress',
                    'Pt. CompltActive',
                    'Pt. CompltFinish'
                )
        ");
    }

}
