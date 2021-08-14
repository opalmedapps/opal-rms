<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../../vendor/autoload.php";

class Profile
{
    public static function removeLegacyProfileColumns(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE Profile
            DROP COLUMN FetchResourcesFromVenues,
            DROP COLUMN FetchResourcesFromClinics,
            DROP COLUMN ShowCheckedOutAppointments;
        ;");
    }

    public static function removeTreatmentVenues(PDO $dbh): void
    {
        $dbh->query("
            UPDATE ProfileOptions
            SET
                Type = 'IntermediateVenue'
            WHERE
                Type = 'TreatmentVenue'
        ");

        $dbh->query("
            ALTER TABLE ProfileOptions
            CHANGE COLUMN Type Type ENUM('ExamRoom','IntermediateVenue','Resource') NOT NULL COLLATE 'latin1_swedish_ci' AFTER Options;
        ");
    }
}
