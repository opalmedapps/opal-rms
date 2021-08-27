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
            DROP COLUMN ShowCheckedOutAppointments
        ;");

        $dbh->query("ALTER TABLE ProfileColumnDefinition DROP COLUMN Speciality");
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

    public static function removeStoredProcedures(PDO $dbh): void
    {
        $dbh->query("DROP PROCEDURE IF EXISTS DeleteProfile");
        $dbh->query("DROP PROCEDURE IF EXISTS SetupProfile");
        $dbh->query("DROP PROCEDURE IF EXISTS UpdateProfileColumns");
        $dbh->query("DROP PROCEDURE IF EXISTS UpdateProfileOptions");
        $dbh->query("DROP PROCEDURE IF EXISTS VerifyProfileColumns");
    }
}
