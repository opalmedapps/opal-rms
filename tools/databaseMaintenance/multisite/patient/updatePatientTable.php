<?php declare(strict_types = 1);

use Orms\DataAccess\PatientAccess;
use Orms\Patient\PatientInterface;
use Orms\Hospital\OIE\Fetch;
use Orms\Patient\Model\Insurance;

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

    static function addSexColumn(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `Patient`
	        ADD COLUMN `Sex` VARCHAR(25) NOT NULL AFTER `DateOfBirth`;
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

    static function migratePatientDemographics(PDO $dbh,bool $runWithChecks = TRUE): int
    {
        //get the list of all patients currently in ORMS
        //right now, there's only RVH mrns in the system
        $queryPatients = $dbh->prepare("
            SELECT
                P.PatientSerNum,
                P.PatientId,
                P.SSN
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
            $mrn       = $p["PatientId"];
            $id        = (int) $p["PatientSerNum"];
            $ramq      = $p["SSN"] ?? NULL;

            try {
                $external = Fetch::getExternalPatientByMrnAndSite($mrn,"RVH") ?? NULL;
            }
            catch(Exception) {
                print_r(["Unknown patient $mrn ($id)"]);
                continue;
            }
            catch(TypeError) {
                print_r(["ADT call failed for $mrn ($id)"]);
                continue;
            }

            if($external === NULL) {
                print_r(["Unknown patient $mrn ($id)"]);
                continue;
            }

            //find the patient in ORMS in order to update it
            $patient = PatientInterface::getPatientById($id) ?? throw new Exception("Unknown patient $mrn");

            if($runWithChecks === TRUE)
            {
                //since there is a possibility of an mrn not being an rvh mrn (due to add-ons being entered manually), we need to match the patient retrieved from the ADT with an additional piece of data
                //start with the ramq
                //we're only receiving ramqs right now, so no need to filter the insurances
                $externalRamq = $external->insurances[0]->number ?? NULL;

                //if patient has no ramq, try using first name and last name instead
                //if the last names match, and the first name from one object is contained in the other, then it's most likely the same patient
                //if nothing matches, skip the patient
                if($ramq === NULL || $externalRamq === NULL || $ramq !== $externalRamq)
                {
                    if($patient->lastName !== $external->lastName) {
                        print_r([
                            "$patient->firstName | $external->firstName",
                            "$patient->lastName | $external->lastName",
                            "$ramq <> $externalRamq",
                            "$mrn ($id)"
                        ]);
                        continue;
                    }

                    if(str_contains($patient->firstName,$external->firstName) === FALSE
                        && str_contains($external->firstName,$patient->firstName) === FALSE
                    ) {
                        print_r([
                            "$patient->firstName | $patient->lastName ",
                            "$external->firstName | $external->lastName",
                            "$ramq <> $externalRamq",
                            "$mrn ($id)"
                        ]);
                        continue;
                    }
                }
            }

            $patient = PatientInterface::updatePatientInformation(
                patient:        $patient,
                firstName:      $external->firstName,
                lastName:       $external->lastName,
                dateOfBirth:    $external->dateOfBirth,
                mrns:           $external->mrns,
                insurances:     $external->insurances
            );
        }

        //return the number of patients that we weren't able to match in the ADT
        $queryPatients->execute();
        return count($queryPatients->fetchAll());
    }
}
