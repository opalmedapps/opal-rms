<?php declare(strict_types = 1);

use Orms\Patient\Patient;
use Orms\Hospital\OIE\Fetch;

require_once __DIR__ ."/../../../../vendor/autoload.php";

class PatientTable
{
    static function addDateOfBirthColumn(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `Patient`
            ADD COLUMN `DateOfBirth` DATETIME NOT NULL AFTER `SSNExpDate`;
        ");
    }

    static function updateSmsSignupDate(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `Patient`
            CHANGE COLUMN `SMSLastUpdated` `SMSLastUpdated` DATETIME NULL DEFAULT NULL AFTER `LastUpdatedUserIP`;
        ");
    }

    static function fixSmsDates(PDO $dbh): void
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

    static function removeDeprecatedPatientColumns(PDO $dbh): void
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

    static function migratePatientDemographics(PDO $dbh): int
    {
        //get the list of all patients currently in ORMS
        //right now, there's only RVH mrns in the system
        $queryPatients = $dbh->prepare("
            SELECT
                P.PatientSerNum AS id,
                P.PatientId AS mrn
            FROM
                Patient P
                LEFT JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            WHERE
                PH.PatientId IS NULL
            ORDER BY
                P.PatientId
        ");
        $queryPatients->execute();
        $patients = $queryPatients->fetchAll();

        //find each patient in the ADT and update the db with the latest data
        foreach($patients as $p)
        {
            $external = Fetch::getExternalPatientByMrnAndSite($p["mrn"],"RVH") ?? NULL;

            if($external === NULL) {
                continue;
            }

            //find the patient in ORMS in order to update it
            $patient = Patient::getPatientById((int) $p["id"]) ?? throw new Exception("Unknown patient {$p['id']}");

            //since there is a possibility of an mrn not being an rvh mrn (due to add-ons being entered manually), we need to match the patient retrieved from the ADT with an additional piece of data
            //start with the ramq
            //we're only receiving ramqs right now, so no need to filter the insurances
            $ramq = $patient->insurances[0]->number ?? NULL;
            $externalRamq = $external->insurances[0]->number ?? NULL;

            //if patient has no ramq, try using first name and last name instead
            //if nothing matches, skip the patient
            if($ramq === NULL || $externalRamq === NULL) {
                if($patient->firstName !== $external->firstName
                    || $patient->lastName !== $external->lastName
                ) {
                    continue;
                }
            }

            $patient = Patient::updateName($patient,$external->firstName,$external->lastName);
            $patient = Patient::updateDateOfBirth($patient,$external->dateOfBirth);

            foreach($external->mrns as $mrn) {
                $patient = Patient::updateMrn($patient,$mrn->mrn,$mrn->site,$mrn->active);
            }
            foreach($external->insurances as $insurance) {
                $patient = Patient::updateInsurance($patient,$insurance->number,$insurance->type,$insurance->expiration,$insurance->active);
            }
        }

        //return the number of patients that we weren't able to match in the ADT
        $queryPatients->execute();
        return count($queryPatients->fetchAll());
    }
}
