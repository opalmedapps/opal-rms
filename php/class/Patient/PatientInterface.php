<?php declare(strict_types = 1);

namespace Orms\Patient;

use Exception;
use Orms\DateTime;
use Orms\DataAccess\PatientAccess;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Insurance;

class PatientInterface
{
    //constants used in the updatePatientInformation() function to separate user provided nulls from default nulls
    private const NO_PHONE_NUMBER = "000-0000-0000";
    private const NO_LANGUAGE = "UNKNOWN_LANG";

    static function getPatientById(int $id): ?Patient
    {
        return self::_fetchPatient($id);
    }

    static function getPatientByMrn(string $mrn,string $site): ?Patient
    {
        $id = PatientAccess::getPatientIdForMrn($mrn,$site);
        return self::_fetchPatient($id);
    }

    static function getPatientByInsurance(string $insuranceNumber,string $type): ?Patient
    {
        $id = PatientAccess::getPatientIdForInsurance($insuranceNumber,$type);
        return self::_fetchPatient($id);
    }

    private static function _fetchPatient(int $id): ?Patient
    {
        return PatientAccess::deserializePatient($id);
    }

    /**
     * Inserts a new patient in the database. The patient must have at least one active mrn.
     * @param Mrn[] $mrns
     * @param Insurance[] $insurances
     */
    static function insertNewPatient(
        string $firstName,
        string $lastName,
        DateTime $dateOfBirth,
        string $sex,
        array $mrns,
        array $insurances
    ): Patient
    {
        $newPatient = new Patient(
            id:                  -1, //id is negative to indicate that the patient doesn't exist in the system
            firstName:           $firstName,
            lastName:            $lastName,
            dateOfBirth:         $dateOfBirth,
            sex:                 $sex,
            phoneNumber:         NULL,
            opalStatus:          0,
            languagePreference:  NULL,
            mrns:                $mrns,
            insurances:          $insurances
        );

        if($newPatient->getActiveMrns() === []) {
            throw new Exception("Failed to create patient with no active mrns");
        }

        PatientAccess::serializePatient($newPatient);

        //patient should exist now, so searching for them will work
        return self::getPatientByMrn($mrns[0]->mrn,$mrns[0]->site) ?? throw new Exception("Failed to create patient");
    }

    /**
     * Finds all patients who have the input phone number and unregisters that phone number. Returns an array of patients who had their numbers removed
     * @return Patient[]
     */
    static function unregisterPhoneNumberFromPatients(string $phoneNumber): array
    {
        //phone number must be exactly 10 digits
        if(!preg_match("/[0-9]{10}/",$phoneNumber)) {
            throw new Exception("Invalid phone number");
        }

        $patients = PatientAccess::getPatientsWithPhoneNumber($phoneNumber);
        $patients = array_filter($patients);

        return array_map(function($x) {
            return self::updatePatientInformation($x,phoneNumber: NULL,languagePreference: NULL);
        },$patients);
    }

    /**
     * Returns a new version of the patient with the specified field(s) updated.
     * @param Mrn[] $mrns
     * @param Insurance[] $insurances
     */
    static function updatePatientInformation(
        Patient $patient,
        string $firstName = NULL,
        string $lastName = NULL,
        Datetime $dateOfBirth = NULL,
        string $sex = NULL,
        ?string $phoneNumber = self::NO_PHONE_NUMBER,
        int $opalStatus = NULL,
        ?string $languagePreference = self::NO_LANGUAGE,
        array $mrns = NULL,
        array $insurances = NULL
    ): Patient
    {
        //since the phone number can be null, we can't tell apart the default null or a user inputted null
        //so we use a class constant to differentiate them
        if($phoneNumber === self::NO_PHONE_NUMBER) {
            $phoneNumber = $patient->phoneNumber;
            $languagePreference = $patient->languagePreference;
        }
        else {
            //phone number must be exactly 10 digits
            if($phoneNumber !== NULL && !preg_match("/[0-9]{10}/",$phoneNumber)) {
                throw new Exception("Invalid phone number");
            }

            //if the patient has a phone number, the language preference must also be specified
            if($phoneNumber !== NULL && ($languagePreference === NULL || $languagePreference === self::NO_LANGUAGE)) {
                throw new Exception("Phone number must have a language preference");
            }
        }

        $newPatient = new Patient(
            id:                  $patient->id,
            firstName:           $firstName ?? $patient->firstName,
            lastName:            $lastName ?? $patient->lastName,
            dateOfBirth:         $dateOfBirth ?? $patient->dateOfBirth,
            sex:                 $sex ?? $patient->sex,
            phoneNumber:         $phoneNumber,
            opalStatus:          $opalStatus ?? $patient->opalStatus,
            languagePreference:  $languagePreference,
            mrns:                $mrns ?? $patient->mrns,
            insurances:          $insurances ?? $patient->insurances
        );

        if($patient->getActiveMrns() === []) {
            throw new Exception("Failed to create patient with no active mrns");
        }

        //overwrite the previous version of the patient in the database with the updated copy
        PatientAccess::serializePatient($newPatient);

        //fetch the updated patient from the database to ensure that we have the latest version of the patient
        return PatientAccess::deserializePatient($patient->id) ?? throw new Exception("Failed to update patient $patient->id");
    }

    /**
     * Merges two patients in the system into a single entry
     * @param Patient $acquirer the patient entry that will remain after the merge
     * @param Patient $target the patient entry that will be merged (and then deleted)
     */
    static function mergePatientEntries(Patient $acquirer,Patient $target): Patient
    {
        return PatientAccess::mergePatientEntries($acquirer,$target);
    }
}
