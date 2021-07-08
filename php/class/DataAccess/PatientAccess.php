<?php declare(strict_types = 1);

namespace Orms\DataAccess;

use PDO;
use Exception;
use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Insurance;

class PatientAccess
{
    // private const PATIENT_TABLE = "Patient"; // TODO: put table and column names as constants at some point...

    static function serializePatient(Patient $patient): void
    {
        $dbh = Database::getOrmsConnection();
        $dbh->beginTransaction();

        try
        {
            $setColumnsSql = "
                SET
                    FirstName           = :fn,
                    LastName            = :ln,
                    DateOfBirth         = :dob,
                    OpalPatient         = :status,
                    SMSAlertNum         = :smsNum,
                    SMSSignupDate       = IF(:smsNum IS NULL,NULL,COALESCE(SMSSignupDate,NOW())),
                    SMSLastUpdated      = NOW(),
                    LanguagePreference  = :language
            ";

            $setValuesSql = [
                ":fn"           => strtoupper($patient->firstName),
                ":ln"           => strtoupper($patient->lastName),
                ":dob"          => $patient->dateOfBirth->format("Y-m-d H:i:s"),
                ":status"       => $patient->opalStatus,
                ":smsNum"       => $patient->phoneNumber,
                ":language"     => $patient->languagePreference
            ];

            //if patient id is -1, insert the patient, otherwise update the already existing patient
            if($patient->id < 0)
            {
                $dbh->prepare("
                    INSERT INTO Patient
                    $setColumnsSql
                ")->execute($setValuesSql);

                $patientId = (int) $dbh->lastInsertId();
            }
            else {
                $dbh->prepare("
                    UPDATE Patient
                    $setColumnsSql
                    WHERE
                        PatientSerNum = :id
                ")->execute(array_merge(
                    $setValuesSql,
                    [":id" => $patient->id]
                ));

                $patientId = $patient->id;
            }

            foreach($patient->mrns as $m) {
                self::_serializeMrn($dbh,$patientId,$m->mrn,$m->site,$m->active);
            }

            foreach($patient->insurances as $i) {
                self::_serializeInsurance($dbh,$patientId,$i->number,$i->type,$i->expiration,$i->active);
            }

        }
        catch(Exception $e) {
            $dbh->rollBack();
            throw $e;
        }

        $dbh->commit();
    }

    static function deserializePatient(int $patientId): ?Patient
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                LastName,
                FirstName,
                DateOfBirth,
                Sex,
                SMSAlertNum,
                OpalPatient,
                LanguagePreference
            FROM
                Patient
            WHERE
                PatientSerNum = ?
        ");
        $query->execute([$patientId]);

        $row = $query->fetchAll()[0] ?? NULL;

        if($row === NULL) return NULL;

        return new Patient(
            id:                    $patientId,
            firstName:             $row["FirstName"],
            lastName:              $row["LastName"],
            dateOfBirth:           new DateTime($row["DateOfBirth"]),
            sex:                   $row["Sex"],
            phoneNumber:           $row["SMSAlertNum"],
            opalStatus:            (int) $row["OpalPatient"],
            languagePreference:    $row["LanguagePreference"],
            mrns:                  self::_deserializeMrnsForPatientId($patientId),
            insurances:            self::_deserializeInsurancesForPatientId($patientId)
        );
    }

    /**
     * Returns a patient id if the mrn is found in the system, otherwise returns 0
     *
     */
    static function getPatientIdForMrn(string $mrn,string $site): int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PH.PatientId
            FROM
                PatientHospitalIdentifier PH
                INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
                    AND H.HospitalCode = :site
            WHERE
                PH.MedicalRecordNumber = :mrn
        ");
        $query->execute([
            ":site" => $site,
            ":mrn"  => $mrn
        ]);

        return (int) ($query->fetchAll()[0]["PatientId"] ?? NULL);
    }

    /**
     * Updates a patient's mrn by comparing it to what the patient has in the database.
     * If the mrn doesn't exist, it is inserted.
     * A patient must always have an active mrn.
     */
    static function _serializeMrn(PDO $dbh,int $patientId,string $mrn,string $site,bool $active): void
    {
        //check if the mrn current exists
        //also get the format that the mrn should have
        $queryExists = $dbh->prepare("
            SELECT
                H.Format,
                PH.Active
            FROM
                Hospital H
                LEFT JOIN PatientHospitalIdentifier PH ON PH.HospitalId = H.HospitalId
                    AND PH.MedicalRecordNumber = :mrn
                    AND PH.PatientId = :pid
            WHERE
                H.HospitalCode = :site
        ");
        $queryExists->execute([
            ":site" => $site,
            ":mrn"  => $mrn,
            ":pid"  => $patientId
        ]);

        $mrnInfo = $queryExists->fetchAll();
        $format = $mrnInfo[0]["Format"] ?? NULL;
        $mrnActive = $mrnInfo[0]["Active"] ?? NULL;

        //check if the format of the incoming mrn is valid
        //if the format is empty or null, the mrn supplied will always match
        if(preg_match("/$format/",$mrn) !== 1) {
            throw new Exception("Invalid mrn format for $mrn | $site");
        }

        //if the mrn doesn't exist, insert the new mrn
        //if it does and the status changed, update it
        if($mrnActive === NULL)
        {
            $dbh->prepare("
                INSERT INTO PatientHospitalIdentifier(
                    PatientId,
                    HospitalId,
                    MedicalRecordNumber,
                    Active
                )
                VALUES(
                    :pid,
                    (SELECT HospitalId FROM Hospital WHERE HospitalCode = :site),
                    :mrn,
                    :active
                )
            ")->execute([
                ":pid"    => $patientId,
                ":mrn"    => $mrn,
                ":site"   => $site,
                ":active" => (int) $active
            ]);
        }
        elseif((bool) $mrnActive !== $active)
        {
            $dbh->prepare("
                UPDATE PatientHospitalIdentifier
                SET
                    Active = :active
                WHERE
                    PatientId = :pid
                    AND MedicalRecordNumber = :mrn
                    AND HospitalId = (SELECT HospitalId FROM Hospital WHERE HospitalCode = :site)
            ")->execute([
                ":pid"    => $patientId,
                ":mrn"    => $mrn,
                ":site"   => $site,
                ":active" => (int) $active
            ]);
        }
    }

    /**
     *
     * @return Mrn[]
     */
    static function _deserializeMrnsForPatientId(int $patientId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PH.MedicalRecordNumber,
                PH.Active,
                PH.PatientId,
                H.HospitalCode
            FROM
                PatientHospitalIdentifier PH
                INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
            WHERE
                PH.PatientId = ?
        ");
        $query->execute([$patientId]);

        return array_map(function($x) {
            return new Mrn(
                $x["MedicalRecordNumber"],
                $x["HospitalCode"],
                (bool) $x["Active"]
            );
        },$query->fetchAll());
    }

    /**
     * Returns a patient id if the insurance is found in the system, otherwise returns 0
     *
     */
    static function getPatientIdForInsurance(string $insuranceNumber,string $insuranceType): int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PI.PatientId
            FROM
                PatientInsuranceIdentifier PI
                INNER JOIN Insurance I ON I.InsuranceId = PI.InsuranceId
                    AND I.InsuranceCode = :type
            WHERE
                PI.InsuranceNumber = :insurance
        ");
        $query->execute([
            ":type"      => $insuranceType,
            ":insurance" => $insuranceNumber
        ]);

        return (int) ($query->fetchAll()[0]["PatientId"] ?? NULL);
    }

    /**
     *
     * @return Insurance[]
     */
    static function _deserializeInsurancesForPatientId(int $patientId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PI.PatientId,
                PI.InsuranceNumber,
                PI.ExpirationDate,
                PI.Active,
                I.InsuranceCode
            FROM
                PatientInsuranceIdentifier PI
                INNER JOIN Insurance I ON I.InsuranceId = PI.InsuranceId
            WHERE
                PI.PatientId = ?
        ");
        $query->execute([$patientId]);

        return array_map(function($x) {
            return new Insurance(
                $x["InsuranceNumber"],
                new DateTime($x["ExpirationDate"]),
                $x["InsuranceCode"],
                (bool) $x["Active"]
            );
        },$query->fetchAll());
    }

    /**
     * Updates a patient's insurance by comparing it to what the patient has in the database.
     * If the insurance doesn't exist, it is inserted
     */
    static function _serializeInsurance(PDO $dbh,int $patientId,string $insuranceNumber,string $insuranceType,DateTime $expirationDate,bool $active): void
    {
        $dbh = Database::getOrmsConnection();

        //check if the mrn current exists
        //also get the format that the insurance should have
        $queryExists = $dbh->prepare("
            SELECT
                I.Format,
                PI.ExpirationDate,
                PI.Active
            FROM
                Insurance I
                LEFT JOIN PatientInsuranceIdentifier PI ON PI.InsuranceId = I.InsuranceId
                    AND PI.InsuranceNumber = :number
                    AND PI.PatientId = :pid
            WHERE
                I.InsuranceCode = :type
        ");
        $queryExists->execute([
            ":type"    => $insuranceType,
            ":number"  => $insuranceNumber,
            ":pid"     => $patientId
        ]);

        $insuranceInfo = $queryExists->fetchAll();
        $format = $insuranceInfo[0]["Format"] ?? NULL;
        $insuranceActive = $insuranceInfo[0]["Active"] ?? NULL;
        $insuranceActiveExpiration = $insuranceInfo[0]["ExpirationDate"] ?? NULL;

        //check if the format of the incoming insurance is valid
        //if the format is empty or null, the insurance supplied will always match
        if(preg_match("/$format/",$insuranceNumber) !== 1) {
            throw new Exception("Invalid insurance format for $insuranceNumber | $insuranceType");
        }

        //if the insurance doesn't exist, insert the new insurance
        //if it does and anything changed, update it
        if($insuranceActive === NULL || $insuranceActiveExpiration === NULL)
        {
            $dbh->prepare("
                INSERT INTO PatientInsuranceIdentifier(
                    PatientId,
                    InsuranceId,
                    InsuranceNumber,
                    ExpirationDate,
                    Active
                )
                VALUES(
                    :pid,
                    (SELECT InsuranceId FROM Insurance WHERE InsuranceCode = :type),
                    :number,
                    :expiration,
                    :active
                )
            ")->execute([
                ":pid"          => $patientId,
                ":number"       => $insuranceNumber,
                ":type"         => $insuranceType,
                ":expiration"   => $expirationDate->format("Y-m-d H:i:s"),
                ":active"       => $active
            ]);
        }
        elseif((bool) $insuranceActive !== $active || new DateTime($insuranceActiveExpiration) != $expirationDate)
        {
            $dbh->prepare("
                UPDATE PatientInsuranceIdentifier
                SET
                    ExpirationDate = :expiration,
                    Active = :active
                WHERE
                    PatientId = :pid
                    AND InsuranceNumber = :number
                    AND InsuranceId = (SELECT InsuranceId FROM Insurance WHERE InsuranceCode = :type)
            ")->execute([
                ":pid"       => $patientId,
                ":number"    => $insuranceNumber,
                ":type"      => $insuranceType,
                "expiration" => $expirationDate->format("Y-m-d H:i:s"),
                ":active"    => $active
            ]);
        }
    }

    /**
     *
     * @return array<Patient|NULL>
     */
    static function getPatientsWithPhoneNumber(string $phoneNumber): array
    {
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
            return self::deserializePatient((int) $x["PatientSerNum"]);
        },$query->fetchAll());
    }

    static function mergePatientEntries(Patient $acquirer,Patient $target): Patient
    {
        //get the list of columns using the Patient id column
        $dbh = Database::getOrmsConnection();

        $foreignKeys = Database::getForeignKeysConnectedToColumn($dbh,"Patient","PatientSerNum");

        $dbh->beginTransaction();
        try
        {
            //update the foreign keys from the target to acquirer patient
            foreach($foreignKeys as $f)
            {
                $dbh->prepare("
                    UPDATE $f[table]
                    SET
                        $f[column] = :newValue
                    WHERE
                        $f[column] = :oldValue
                ")->execute([
                    ":newValue" => $acquirer->id,
                    ":oldValue" => $target->id
                ]);
            }

            //update acquirer patient; merge any information that might have been added to the duplicate
            $dbh->prepare("
                UPDATE Patient Acquirer
                INNER JOIN Patient Target ON Target.PatientSerNum = :targetId
                SET
                    Acquirer.SMSAlertNum        = COALESCE(Acquirer.SMSAlertNum,Target.SMSAlertNum),
                    Acquirer.SMSSignupDate      = COALESCE(Acquirer.SMSSignupDate,Target.SMSSignupDate),
                    Acquirer.SMSLastUpdated     = COALESCE(Acquirer.SMSLastUpdated,Target.SMSLastUpdated),
                    Acquirer.OpalPatient        = COALESCE(Acquirer.OpalPatient,Target.OpalPatient),
                    Acquirer.LanguagePreference = COALESCE(Acquirer.LanguagePreference,Target.LanguagePreference)
                WHERE
                    Acquirer.PatientSerNum = :acquirerId
            ")->execute([
                ":acquirerId" => $acquirer->id,
                ":targetId"   => $target->id
            ]);

            //delete duplicate patient entry
            $dbh->prepare("
                DELETE FROM Patient
                WHERE
                    Patient.PatientSerNum = ?
            ")->execute([$target->id]);
        }
        catch(Exception $e) {
            $dbh->rollBack();
            throw $e;
        }

        $dbh->commit();

        return self::deserializePatient($acquirer->id) ?? throw new Exception("Failed to merge patients");
    }

}
