<?php

declare(strict_types=1);

namespace Orms\Patient;

use Exception;
use Orms\ApplicationException;
use Orms\DataAccess\PatientAccess;
use Orms\DateTime;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\PatientMeasurement;

class PatientInterface
{
    //constants used in the updatePatientInformation() function to separate user provided nulls from default nulls
    private const NO_PHONE_NUMBER = "000-0000-0000";
    private const NO_LANGUAGE = "UNKNOWN_LANG";

    public static function getPatientById(int $id): ?Patient
    {
        return self::_fetchPatient($id);
    }

    public static function getPatientByMrn(string $mrn, string $site): ?Patient
    {
        $id = PatientAccess::getPatientIdForMrn($mrn, $site);
        return self::_fetchPatient($id);
    }

    public static function getPatientByInsurance(string $insuranceNumber, string $type): ?Patient
    {
        $id = PatientAccess::getPatientIdForInsurance($insuranceNumber, $type);
        return self::_fetchPatient($id);
    }

    public static function getPatientByPhoneNumber(string $phoneNumber): ?Patient
    {
        return PatientAccess::getPatientsWithPhoneNumber($phoneNumber)[0] ?? null;
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
    public static function insertNewPatient(
        string $firstName,
        string $lastName,
        DateTime $dateOfBirth,
        string $sex,
        array $mrns,
        array $insurances
    ): void
    {
        $newPatient = new Patient(
            id:                  -1, //id is negative to indicate that the patient doesn't exist in the system
            firstName:           $firstName,
            lastName:            $lastName,
            dateOfBirth:         $dateOfBirth,
            sex:                 $sex,
            phoneNumber:         null,
            opalStatus:          0,
            opalUUID:            '',
            languagePreference:  null,
            mrns:                $mrns,
            insurances:          $insurances
        );

        if($newPatient->getActiveMrns() === []) {
            throw new ApplicationException(ApplicationException::NO_ACTIVE_MRNS,"Failed to create patient with no active mrns");
        }

        PatientAccess::serializePatient($newPatient);
    }

    /**
     * Finds all patients who have the input phone number and unregisters that phone number. Returns an array of patients who had their numbers removed
     * @return Patient[]
     */
    public static function unregisterPhoneNumberFromPatients(string $phoneNumber): array
    {
        //phone number must be exactly 10 digits
        if(!preg_match("/[0-9]{10}/", $phoneNumber)) {
            throw new Exception("Invalid phone number");
        }

        $patients = PatientAccess::getPatientsWithPhoneNumber($phoneNumber);
        $patients = array_filter($patients);

        return array_map(fn($x) => self::updatePatientInformation($x, phoneNumber: null, languagePreference: null), $patients);
    }

    /**
     * Returns a new version of the patient with the specified field(s) updated.
     * @param Mrn[] $mrns
     * @param Insurance[] $insurances
     */
    public static function updatePatientInformation(
        Patient $patient,
        string $firstName = null,
        string $lastName = null,
        Datetime $dateOfBirth = null,
        string $sex = null,
        ?string $phoneNumber = self::NO_PHONE_NUMBER,
        int $opalStatus = null,
        string $opalUUID = null,
        ?string $languagePreference = self::NO_LANGUAGE,
        array $mrns = [],
        array $insurances = null
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
            if($phoneNumber !== null && !preg_match("/[0-9]{10}/", $phoneNumber)) {
                throw new Exception("Invalid phone number");
            }

            //if the patient has a phone number, the language preference must also be specified
            if($phoneNumber !== null && ($languagePreference === null || $languagePreference === self::NO_LANGUAGE)) {
                throw new Exception("Phone number must have a language preference");
            }
        }

        //combine the patient's mrns with the new incoming ones
        //filter the new mrns and then add them back to capture any changes
        $newMrns = array_merge(
            array_udiff($patient->mrns,$mrns,fn($x,$y) => [$x->mrn,$x->site] <=> [$y->mrn,$y->site]),
            $mrns
        );

        //each mrn in the ADT may contain a different insurance number, or the same number but with a different expiration date
        //for each insurance number, check if it's already in the system and use the newest expiration date
        if($insurances !== null) {
            $insurances = array_values(array_filter($insurances,function($insurance) use ($patient) {
                $sameInsurance = array_values(array_filter($patient->insurances,fn($x) => [$x->number,$x->type] === [$insurance->number,$insurance->type]))[0] ?? null;
                return $sameInsurance === null || $sameInsurance->expiration <= $insurance->expiration;
            }));
        }

        $newPatient = new Patient(
            id:                  $patient->id,
            firstName:           $firstName ?? $patient->firstName,
            lastName:            $lastName ?? $patient->lastName,
            dateOfBirth:         $dateOfBirth ?? $patient->dateOfBirth,
            sex:                 $sex ?? $patient->sex,
            phoneNumber:         $phoneNumber,
            opalStatus:          $opalStatus ?? $patient->opalStatus,
            opalUUID:            $opalUUID ?? $patient->opalUUID,
            languagePreference:  $languagePreference,
            mrns:                $newMrns,
            insurances:          $insurances ?? $patient->insurances
        );

        if($newPatient->getActiveMrns() === []) {
            throw new ApplicationException(ApplicationException::NO_ACTIVE_MRNS,"Failed to update patient with no active mrns");
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
    public static function mergePatientEntries(Patient $acquirer, Patient $target): void
    {
        PatientAccess::mergePatientEntries($acquirer, $target);
    }

    /**
     * Separates a patient entry into two separate entries
     * @param Mrn $unlinkedMrn
     */
    public static function unlinkPatientEntries(Patient $originalEntry,Mrn $unlinkedMrn): void
    {
        //delete the unlinked mrn from the original patient so it's available to the unlinked patient
        PatientAccess::deleteMrn($originalEntry,$unlinkedMrn);

        //create a new entry for the unlinked patient using the original patient demographics
        PatientInterface::insertNewPatient(
            firstName:   $originalEntry->firstName,
            lastName:    $originalEntry->lastName,
            dateOfBirth: $originalEntry->dateOfBirth,
            sex:         $originalEntry->sex,
            mrns:        [$unlinkedMrn],
            insurances:  [] //don't re-use the insurances as an insurance number can only belong to one patient
        );

        //patient should exist now, so searching for them will work
        $unlinkedPatient = self::getPatientByMrn($unlinkedMrn->mrn, $unlinkedMrn->site) ?? throw new Exception("Failed to create patient");

        PatientAccess::unlinkPatientEntries($originalEntry,$unlinkedPatient);
    }

    /**
     *
     * @param Mrn[] $mrns
     * @return Patient[]
     */
    public static function getPatientsFromMrns(array $mrns): array
    {
        $patients = array_map(fn($x) => self::getPatientByMrn($x->mrn, $x->site), $mrns);

        $patients = array_values(array_filter($patients)); //filter nulls
        $patients = array_unique($patients, SORT_REGULAR); //filter duplicates
        usort($patients, fn($a, $b) => $a->id <=> $b->id); //sort by oldest record first in case of merge

        return $patients;
    }

    public static function deactivateInsurance(Patient $patient,string $insuranceType): void
    {
        $insurances = array_values(array_filter($patient->insurances,fn($x) => $x->type === $insuranceType));
        foreach($insurances as $insurance) {
            PatientAccess::deleteInsurance($patient,$insurance);
        }
    }

    /**
     *
     * @return PatientMeasurement[]
     */
    public static function getPatientMeasurements(Patient $patient): array
    {
        return PatientAccess::deserializePatientMeasurements($patient);
    }

    public static function insertPatientMeasurement(Patient $patient, float $height, float $weight, float $bsa, string $appointmentSourceId, string $appointmentSourceSystem): void
    {
        PatientAccess::serializePatientMeasurement($patient, $height, $weight, $bsa, $appointmentSourceId, $appointmentSourceSystem);
    }

    public static function isInsuranceValid(string $insuranceNumber, string $insuranceType): bool
    {
        return PatientAccess::isInsuranceValid($insuranceNumber, $insuranceType);
    }

    public static function getLastQuestionnaireReview(Patient $patient): ?DateTime
    {
        return PatientAccess::getLastQuestionnaireReview($patient);
    }

    public static function insertQuestionnaireReview(Patient $patient, string $user): void
    {
        PatientAccess::insertQuestionnaireReview($patient,$user);
    }
}
