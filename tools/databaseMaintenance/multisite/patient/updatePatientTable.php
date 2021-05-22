<?php declare(strict_types = 1);

use Orms\Patient\Patient;
use Orms\Hospital\OIE\Fetch;

require_once __DIR__ ."/../../../../vendor/autoload.php";

function addDateOfBirthColumn(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `Patient`
        ADD COLUMN `DateOfBirth` DATETIME NOT NULL AFTER `SSNExpDate`;
    ");
}

function updateSmsSignupDate(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `Patient`
        CHANGE COLUMN `SMSLastUpdated` `SMSLastUpdated` DATETIME NULL DEFAULT NULL AFTER `LastUpdatedUserIP`;
    ");
}

function fixSmsDates(PDO $dbh): void
{
    $dbh->query("
        UPDATE Patient
        SET
            SMSLastUpdated = NULL
        WHERE
            SMSLastUpdated = '0000-00-00 00:00:00'
    ");

    $dbh->query("
        UPDATE Patient
        SET
            SMSSignupDate = NULL
        WHERE
            SMSSignupDate = '0000-00-00 00:00:00'
    ");
}

function removeDeprecatedColumns(PDO $dbh): void
{
    $dbh->query("
        ALTER TABLE `Patient`
        DROP COLUMN `SSN`,
        DROP COLUMN `SSNExpDate`,
        DROP COLUMN `PatientId`,
        DROP COLUMN `PatientId_MGH`,
        DROP COLUMN `Email`,
        DROP COLUMN `LastUpdatedUserIP`,
        DROP COLUMN `Comment`,
        DROP INDEX `OpalPatient_IDX`,
        DROP INDEX `combined_index`;
    ");
}

function migratePatientDemographics(PDO $dbh): void
{
    //get the list of all patients currently in ORMS
    //right now, there's only RVH mrns in the system
    $queryPatients = $dbh->prepare("
        SELECT
            PatientSerNum AS id,
            PatientId AS mrn
        FROM
            Patient
        ORDER BY
            PatientId
    ");
    $queryPatients->execute();
    $patients = $queryPatients->fetchAll();

    //find each patient in the ADT and update the db with the latest data
    foreach($patients as $p)
    {
        $external = Fetch::getExternalPatientByMrnAndSite($p["mrn"],"RVH") ?? throw new Exception("Unknown patient RVH-{p['mrn']}");

        //find the patient in ORMS in order to update it
        $patient = Patient::getPatientById((int) $p["id"]) ?? throw new Exception("Unknown patient {$p['id']}");

        $patient = Patient::updateName($patient,$external->firstName,$external->lastName);
        $patient = Patient::updateDateOfBirth($patient,$external->dateOfBirth);

        foreach($external->mrns as $mrn) {
            $patient = Patient::updateMrn($patient,$mrn->mrn,$mrn->site,$mrn->active);
        }
        foreach($external->insurances as $insurance) {
            $patient = Patient::updateInsurance($patient,$insurance->number,$insurance->type,$insurance->expiration,$insurance->active);
        }
    }
}
