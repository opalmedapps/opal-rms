<?php declare(strict_types = 1);

namespace Orms\Patient;

use Exception;

use Orms\DataAccess\PatientAccess;
use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\Patient\Internal\Mrn;
use Orms\Patient\Internal\Insurance;

class PatientInterface
{
    static function getPatientById(int $id): ?Patient
    {
        return self::_fetchPatient($id);
    }

    static function getPatientByMrn(string $mrn,string $site): ?Patient
    {
        $id = Mrn::getPatientIdForMrn($mrn,$site);
        return self::_fetchPatient($id);
    }

    static function getPatientByInsurance(string $insuranceNumber,string $type): ?Patient
    {
        $id = Insurance::getPatientIdForInsurance($insuranceNumber,$type);
        return self::_fetchPatient($id);
    }

    private static function _fetchPatient(int $id): ?Patient
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                LastName,
                FirstName,
                DateOfBirth,
                SMSAlertNum,
                OpalPatient,
                LanguagePreference
            FROM
                Patient
            WHERE
                PatientSerNum = ?
        ");
        $query->execute([$id]);

        $row = $query->fetchAll()[0] ?? NULL;

        if($row === NULL) return NULL;

        return new Patient(
            id:                    $id,
            firstName:             $row["FirstName"],
            lastName:              $row["LastName"],
            dateOfBirth:           new DateTime($row["DateOfBirth"]),
            phoneNumber:           $row["SMSAlertNum"],
            opalPatient:           (int) $row["OpalPatient"],
            languagePreference:    $row["LanguagePreference"],
            mrns:                  Mrn::getMrnsForPatientId($id),
            insurances:            Insurance::getInsurancesForPatientId($id)
        );
    }

    /**
     * Inserts a new patient in the database. The patient must have at least one active mrn.
     * @param list<array{mrn: string,site: string,active: bool}> $mrns
     * @param list<array{number: string,expiration: \Orms\DateTime,type: string,active: bool}> $insurances
     */
    static function insertNewPatient(
        string $firstName,
        string $lastName,
        DateTime $dateOfBirth,
        array $mrns,
        array $insurances
    ): Patient
    {
        $newPatient = new Patient(
            id                  : -1, //id is negative to indicate that the patient doesn't exist in the system
            firstName           : $firstName,
            lastName            : $lastName,
            dateOfBirth         : $dateOfBirth,
            phoneNumber         : NULL,
            opalPatient         : 0,
            languagePreference  : NULL,
            mrns                : array_map(fn($x) => new Mrn(...$x),$mrns),
            insurances          : array_map(fn($x) => new Insurance(...$x),$insurances)
        );

        if($newPatient->getActiveMrns() === []) {
            throw new Exception("Failed to create patient with no active mrns");
        }

        PatientAccess::serializePatient($newPatient);

        //patient should exist now, so searching for them will work
        return self::getPatientByMrn($mrns[0]["mrn"],$mrns[0]["site"]) ?? throw new Exception("Failed to create patient");
    }

    static function updateName(Patient $patient,string $firstName,string $lastName): Patient
    {
        $newPatient = new Patient(
            id:                    $patient->id,
            firstName:             $firstName,
            lastName:              $lastName,
            dateOfBirth:           $patient->dateOfBirth,
            phoneNumber:           $patient->phoneNumber,
            opalPatient:           $patient->opalPatient,
            languagePreference:    $patient->languagePreference,
            mrns:                  $patient->mrns,
            insurances:            $patient->insurances
        );

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update name for patient $patient->id");
    }

    static function updateDateOfBirth(Patient $patient,DateTime $dateOfBirth): Patient
    {
        $newPatient = new Patient(
            id:                    $patient->id,
            firstName:             $patient->firstName,
            lastName:              $patient->lastName,
            dateOfBirth:           $dateOfBirth,
            phoneNumber:           $patient->phoneNumber,
            opalPatient:           $patient->opalPatient,
            languagePreference:    $patient->languagePreference,
            mrns:                  $patient->mrns,
            insurances:            $patient->insurances
        );

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update date of birth for patient $patient->id");
    }

    static function updateOpalStatus(Patient $patient,int $opalStatus): Patient
    {
        $newPatient = new Patient(
            id:                    $patient->id,
            firstName:             $patient->firstName,
            lastName:              $patient->lastName,
            dateOfBirth:           $patient->dateOfBirth,
            phoneNumber:           $patient->phoneNumber,
            opalPatient:           $opalStatus,
            languagePreference:    $patient->languagePreference,
            mrns:                  $patient->mrns,
            insurances:            $patient->insurances,
        );

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update opal status for patient $patient->id");
    }

    /**
     * Updates a patient's phone number. Can also remove a patient's phone number.
     *
     */
    static function updatePhoneNumber(Patient $patient,?string $phoneNumber,?string $languagePreference): Patient
    {
        //the phone number must be either null or exactly 10 digits
        if($phoneNumber !== NULL && preg_match("/[0-9]{10}/",$phoneNumber) === FALSE) {
            throw new Exception("Invalid phone number");
        }

        $newPatient = new Patient(
            id:                    $patient->id,
            firstName:             $patient->firstName,
            lastName:              $patient->lastName,
            dateOfBirth:           $patient->dateOfBirth,
            phoneNumber:           $phoneNumber,
            opalPatient:           $patient->opalPatient,
            languagePreference:    $languagePreference,
            mrns:                  $patient->mrns,
            insurances:            $patient->insurances
        );

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update phone number for $patient->id");
    }

    /**
     *
     * @param list<array{mrn: string,site: string,active: bool}> $mrns
     */
    static function updateMrns(Patient $patient,array $mrns): Patient
    {
        $newPatient = new Patient(
            id:                  $patient->id,
            firstName:           $patient->firstName,
            lastName:            $patient->lastName,
            dateOfBirth:         $patient->dateOfBirth,
            phoneNumber:         $patient->phoneNumber,
            opalPatient:         $patient->opalPatient,
            languagePreference:  $patient->languagePreference,
            mrns:                array_map(fn($x) => new Mrn(...$x),$mrns),
            insurances:          $patient->insurances
        );

        if($patient->getActiveMrns() === []) {
            throw new Exception("Failed to create patient with no active mrns");
        }

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update mrns for patient $patient->id");
    }

    /**
     *
     * @param list<array{number: string,expiration: \Orms\DateTime,type: string,active: bool}> $insurances
     */
    static function updateInsurances(Patient $patient,array $insurances): Patient
    {
        $newPatient = new Patient(
            id:                  $patient->id,
            firstName:           $patient->firstName,
            lastName:            $patient->lastName,
            dateOfBirth:         $patient->dateOfBirth,
            phoneNumber:         $patient->phoneNumber,
            opalPatient:         $patient->opalPatient,
            languagePreference:  $patient->languagePreference,
            mrns:                $patient->mrns,
            insurances:          array_map(fn($x) => new Insurance(...$x),$insurances)
        );

        PatientAccess::serializePatient($newPatient);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update insurances for patient $patient->id");
    }

    /**
     * Finds all patients who have the input phone number and unregisters that phone number. Returns an array of patients who had their numbers removed
     * @return Patient[]
     */
    static function unregisterPhoneNumberFromPatients(string $phoneNumber): array
    {
        //phone number must be exactly 10 digits
        if(!preg_match("/[0-9]{10}/",$phoneNumber)) throw new Exception("Invalid phone number");

        //find all patients with the phone number
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PatientSerNum
            FROM
                Patient
            WHERE
                SMSAlertNum = ?
        ");
        $query->execute([$phoneNumber]);

        return array_map(function($x) {
            $patient = self::getPatientById((int) $x["PatientSerNum"]) ?? throw new Exception("Unknown patient");
            return self::updatePhoneNumber($patient,NULL,NULL);
        },$query->fetchAll());
    }

    /**
     * Merges two patients in the system into a single entry
     * @param Patient $acquirer the patient entry that will remain after the merge
     * @param Patient $target the patient entry that will be merged (and then deleted)
     */
    // static function mergePatientEntries(Patient $acquirer,Patient $target): Patient
    // {
    //     //get the list of columns using the Patient id column
    //     $dbh = Database::getOrmsConnection();

    //     $foreignKeys = Database::getForeignKeysConnectedToColumn($dbh,"Patient","PatientSerNum");

    //     foreach($foreignKeys as $f)
    //     {
    //         $dbh->prepare("
    //             UPDATE $f[table]
    //             SET
    //                 $f[column] = :newValue
    //             WHERE
    //                 $f[column] = :oldValue
    //         ")->execute([
    //             ":newValue" => $acquirer->id,
    //             ":oldValue" => $target->id
    //         ]);
    //     }

    //     //update original patient; merge any information that might have been added to the duplicate
    //     $dbh->prepare("
    //         UPDATE Patient Acquirer
    //         INNER JOIN Patient Target ON Target.PatientSerNum = :targetId
    //         SET
    //             Acquirer.SMSAlertNum        = COALESCE(Acquirer.SMSAlertNum,Target.SMSAlertNum),
    //             Acquirer.SMSSignupDate      = COALESCE(Acquirer.SMSSignupDate,Target.SMSSignupDate),
    //             Acquirer.SMSLastUpdate      = COALESCE(Acquirer.SMSLastUpdate,Target.SMSLastUpdate),
    //             Acquirer.OpalPatient        = COALESCE(Acquirer.OpalPatient,Target.OpalPatient),
    //             Acquirer.LanguagePreference = COALESCE(Acquirer.LanguagePreference,Target.LanguagePreference)
    //         WHERE
    //             Acquirer.PatientSerNum = :acquirerId
    //     ")->execute([
    //         ":acquirerId" => $acquirer->id,
    //         ":targetId"   => $target->id
    //     ]);

    //     //delete duplicate patient entry
    //     $dbh->prepare("
    //         DELETE FROM Patient
    //         WHERE
    //             Patient.PatientSerNum = ?
    //     ")->execute([$target->id]);

    //     return self::getPatientById($acquirer->id) ?? throw new Exception("Failed to merge patients");
    // }
}
