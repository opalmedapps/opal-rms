<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Exception;
use Orms\ApplicationException;
use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\External\OIE\Fetch;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\PatientMeasurement;
use PDO;

class PatientAccess
{
    // private const PATIENT_TABLE = "Patient"; // TODO: put table and column names as constants at some point...

    public static function serializePatient(Patient $patient): void
    {
        $dbh = Database::getOrmsConnection();

        $inNestedTransation = $dbh->inTransaction();
        $inNestedTransation ?: $dbh->beginTransaction();

        try
        {
            $setColumnsSql = "
                SET
                    FirstName           = :fn,
                    LastName            = :ln,
                    DateOfBirth         = :dob,
                    Sex                 = :sex,
                    OpalPatient         = :status,
                    SMSAlertNum         = :smsNum,
                    SMSSignupDate       = IF(:smsNum IS NULL,NULL,COALESCE(SMSSignupDate,NOW())),
                    SMSLastUpdated      = IF(:smsNum <=> SMSAlertNum AND :language <=> LanguagePreference,SMSLastUpdated,NOW()),
                    LanguagePreference  = :language
            ";

            $setValuesSql = [
                ":fn"           => mb_strtoupper($patient->firstName),
                ":ln"           => mb_strtoupper($patient->lastName),
                ":dob"          => $patient->dateOfBirth->format("Y-m-d H:i:s"),
                ":sex"          => $patient->sex,
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
                self::_serializeMrn($dbh, $patientId, $m->mrn, $m->site, $m->active);
            }

            foreach($patient->insurances as $i) {
                self::_serializeInsurance($dbh, $patientId, $i->number, $i->type, $i->expiration, $i->active);
            }

        }
        catch(Exception $e) {
            $inNestedTransation ?: $dbh->rollBack();
            throw $e;
        }

        $inNestedTransation ?: $dbh->commit();
    }

    public static function deserializePatient(int $patientId): ?Patient
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

        $row = $query->fetchAll()[0] ?? null;

        if($row === null) return null;

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
    public static function getPatientIdForMrn(string $mrn, string $site): int
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

        return (int) ($query->fetchAll()[0]["PatientId"] ?? null);
    }

    /**
     * Updates a patient's mrn by comparing it to what the patient has in the database.
     * If the mrn doesn't exist, it is inserted.
     * A patient must always have an active mrn.
     */
    public static function _serializeMrn(PDO $dbh, int $patientId, string $mrn, string $site, bool $active): void
    {
        //check if the mrn current exists
        //also get the format that the mrn should have
        $queryExists = $dbh->prepare("
            SELECT
                H.HospitalId,
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

        $hospitalId = $mrnInfo[0]["HospitalId"] ?? null;
        $format = $mrnInfo[0]["Format"] ?? null;
        $mrnActive = $mrnInfo[0]["Active"] ?? null;

        //if the hospital type doesn't exist in the database, reject the mrn
        if($hospitalId === null) {
            throw new ApplicationException(ApplicationException::UNKNOWN_MRN_TYPE, "Unknown hospital code type $site");
        }

        //check if the format of the incoming mrn is valid
        //if the format is empty or null, the mrn supplied will always match
        if(preg_match("/$format/", $mrn) !== 1) {
            throw new ApplicationException(ApplicationException::INVALID_MRN_FORMAT, "Invalid mrn format for $mrn | $site");
        }

        //if the mrn doesn't exist, insert the new mrn
        //if it does and the status changed, update it
        if($mrnActive === null)
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
    public static function _deserializeMrnsForPatientId(int $patientId): array
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
        }, $query->fetchAll());
    }

    public static function deleteMrn(Patient $patient,Mrn $mrn): void
    {
        $dbh = Database::getOrmsConnection();
        $dbh->beginTransaction();

        try
        {
            $dbh->prepare("
                DELETE FROM
                    PatientHospitalIdentifier
                WHERE
                    PatientId = :pid
                    AND MedicalRecordNumber = :mrn
                    AND HospitalId = (SELECT HospitalId FROM Hospital WHERE HospitalCode = :site)
            ")->execute([
                ":pid"  => $patient->id,
                ":mrn"  => $mrn->mrn,
                ":site" => $mrn->site
            ]);

            if(array_filter(self::_deserializeMrnsForPatientId($patient->id),fn($x) => $x->active === true) === []) {
                throw new ApplicationException(ApplicationException::NO_ACTIVE_MRNS,"Failed to update patient with no active mrns");
            }
        }
        catch(Exception $e) {
            $dbh->rollBack();
            throw $e;
        }

        $dbh->commit();
    }

    /**
     * Returns a patient id if the insurance is found in the system, otherwise returns 0
     *
     */
    public static function getPatientIdForInsurance(string $insuranceNumber, string $insuranceType): int
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

        return (int) ($query->fetchAll()[0]["PatientId"] ?? null);
    }

    /**
     *
     * @return Insurance[]
     */
    public static function _deserializeInsurancesForPatientId(int $patientId): array
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
        }, $query->fetchAll());
    }

    /**
     * Updates a patient's insurance by comparing it to what the patient has in the database.
     * If the insurance doesn't exist, it is inserted
     */
    public static function _serializeInsurance(PDO $dbh, int $patientId, string $insuranceNumber, string $insuranceType, DateTime $expirationDate, bool $active): void
    {
        //check if the insurance current exists
        //also get the format that the insurance should have
        $queryExists = $dbh->prepare("
            SELECT
                I.Format,
                I.InsuranceId,
                PI.ExpirationDate,
                PI.Active,
                PI.PatientId
            FROM
                Insurance I
                LEFT JOIN PatientInsuranceIdentifier PI ON PI.InsuranceId = I.InsuranceId
                    AND PI.InsuranceNumber = :number
            WHERE
                I.InsuranceCode = :type
        ");
        $queryExists->execute([
            ":type"    => $insuranceType,
            ":number"  => $insuranceNumber
        ]);

        $insuranceInfo = $queryExists->fetchAll();

        $insuranceId                = $insuranceInfo[0]["InsuranceId"] ?? null;
        $format                     = $insuranceInfo[0]["Format"] ?? null;
        $insuranceActive            = $insuranceInfo[0]["Active"] ?? null;
        $insuranceActiveExpiration  = $insuranceInfo[0]["ExpirationDate"] ?? null;
        $currentPatientId           = (int) ($insuranceInfo[0]["PatientId"] ?? null);

        //if the insurance type doesn't exist in the database, reject the insurance
        if($insuranceId === null) {
            throw new ApplicationException(ApplicationException::UNKNOWN_INSURANCE_TYPE, "Unknown insurance type $insuranceType");
        }

        //check if the format of the incoming insurance is valid
        //if the format is empty or null, the insurance supplied will always match
        if(preg_match("/$format/", $insuranceNumber) !== 1) {
            throw new ApplicationException(ApplicationException::INVALID_INSURANCE_FORMAT, "Invalid insurance format for $insuranceNumber | $insuranceType");
        }

        //if the insurance exists but is attached to another patient, reject it
        if($currentPatientId !== 0 && $currentPatientId !== $patientId) {
            throw new ApplicationException(ApplicationException::INSURANCE_UNIQUENESS_VIOLATION, "Insurance $insuranceNumber | $insuranceType already exists for another patient");
        }

        //if the insurance doesn't exist, insert the new insurance
        //if it does and anything changed, update it
        if($insuranceActive === null || $insuranceActiveExpiration === null)
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
                    :insuranceId,
                    :number,
                    :expiration,
                    :active
                )
            ")->execute([
                ":pid"          => $patientId,
                ":number"       => $insuranceNumber,
                ":insuranceId"  => $insuranceId,
                ":expiration"   => $expirationDate->format("Y-m-d H:i:s"),
                ":active"       => $active
            ]);
        }
        elseif((bool) $insuranceActive !== $active || new DateTime($insuranceActiveExpiration) !== $expirationDate)
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

    public static function isInsuranceValid(string $insuranceNumber, string $insuranceType): bool
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                Format
            FROM
                Insurance
            WHERE
                InsuranceCode = :type
        ");
        $query->execute([
            ":type" => $insuranceType
        ]);

        $format = $query->fetchAll()[0]["Format"] ?? null;

        return ($format !== null) && (preg_match("/$format/", $insuranceNumber) === 1);
    }

    /**
     *
     * @return array<?Patient>
     */
    public static function getPatientsWithPhoneNumber(string $phoneNumber): array
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

        return array_map(fn($x) => self::deserializePatient((int) $x["PatientSerNum"]), $query->fetchAll());
    }

    public static function mergePatientEntries(Patient $acquirer, Patient $target): void
    {
        //get the list of columns using the Patient id column
        $dbh = Database::getOrmsConnection();

        $foreignKeys = Database::getForeignKeysConnectedToColumn($dbh, "Patient", "PatientSerNum");

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
    }

    public static function unlinkPatientEntries(Patient $originalPatient,Patient $newPatient): void
    {
        $dbh = Database::getOrmsConnection();
        $dbh->beginTransaction();

        try
        {
            //split appointments
            $queryAppointments = $dbh->prepare("
                SELECT
                    AppointmentSerNum,
                    AppointId,
                    AppointSys
                FROM
                    MediVisitAppointmentList
                WHERE
                    PatientSerNum = ?
            ");
            $queryAppointments->execute([$originalPatient->id]);

            $appointments = array_map(function($x) use ($originalPatient,$newPatient) {
                [$mrn,$site] = Fetch::getMrnSiteOfAppointment($x["AppointId"],$x["AppointSys"]);

                if($mrn === null || $site === null) {
                    throw new Exception("Unknown appointment");
                }

                $mrnBelongsToNewPatient = (bool) array_values(array_filter($newPatient->mrns,fn($x) => $x->mrn === $mrn && $x->site === $site));

                return [
                    "appointmentId" => (int) $x["AppointmentSerNum"],
                    "sourceId"      => $x["AppointId"],
                    "sourceSystem"  => $x["AppointSys"],
                    "patientId"     => ($mrnBelongsToNewPatient === true) ? $newPatient->id : $originalPatient->id,
                    "mrn"           => $mrn,
                    "site"          => $site
                ];
            },$queryAppointments->fetchAll());

            $updateAppointment = $dbh->prepare("
                UPDATE MediVisitAppointmentList
                SET
                    PatientSerNum = :pid
                WHERE
                    AppointmentSerNum = :aid
            ");

            //split measurements
            $updateMeasurement = $dbh->prepare("
                UPDATE PatientMeasurement
                SET
                    PatientSer = :pid
                WHERE
                    AppointmentId = :sourceId
            ");

            foreach($appointments as $app)
            {
                $updateAppointment->execute([
                    ":pid" => $app["patientId"],
                    ":aid" => $app["appointmentId"]
                ]);

                $updateMeasurement->execute([
                    ":pid"      => $app["patientId"],
                    ":sourceId" => $app["sourceSystem"] ."-". $app["sourceId"]
                ]);
            }

            //split diagnoses
            $updateDiagnosis = $dbh->prepare("
                UPDATE PatientDiagnosis
                SET
                    PatientSerNum = :pid
                WHERE
                    RecordedMrn = :mrn
            ");

            foreach($newPatient->mrns as $mrn)
            {
                $updateDiagnosis->execute([
                    ":pid" => $newPatient->id,
                    ":mrn" => $mrn->site ."-". $mrn->mrn
                ]);
            }
        }
        catch(Exception $e) {
            $dbh->rollBack();
            throw $e;
        }

        $dbh->commit();
    }

    /**
     *
     * @return PatientMeasurement[]
     */
    public static function deserializePatientMeasurements(Patient $patient): array
    {
        //gets the lastest measurement taken during each date to take into account the fact that a patient in be reweighed (in case of an error, etc)
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PM.PatientMeasurementSer,
                PM.Date,
                PM.Time,
                PM.Weight,
                PM.Height,
                PM.BSA,
                PM.PatientId,
                PM.AppointmentId
            FROM
                PatientMeasurement PM
                INNER JOIN (
                    SELECT
                        MM.PatientMeasurementSer
                    FROM
                        PatientMeasurement MM
                        INNER JOIN Patient P ON P.PatientSerNum = MM.PatientSer
                            AND P.PatientSerNum = :id
                    GROUP BY
                        MM.Date
                    ORDER BY
                        MM.Date DESC,
                        MM.Time DESC
                ) AS PMM ON PMM.PatientMeasurementSer = PM.PatientMeasurementSer
            ORDER BY PM.Date
        ");
        $query->execute([
            ":id" => $patient->id
        ]);

        return array_map(function($x) {
            return new PatientMeasurement(
                id:              $x["PatientMeasurementSer"],
                appointmentId:   $x["AppointmentId"],
                mrnSite:         $x["PatientId"],
                datetime:        new DateTime($x["Date"] ." ". $x["Time"]),
                weight:          (float) $x["Weight"],
                height:          (float) $x["Height"],
                bsa:             (float) $x["BSA"],
            );
        }, $query->fetchAll());
    }

    public static function serializePatientMeasurement(Patient $patient, float $height, float $weight, float $bsa, string $appointmentSourceId, string $appointmentSourceSystem): void
    {
        Database::getOrmsConnection()->prepare("
            INSERT INTO PatientMeasurement
            SET
                PatientSer      = :pSer,
                Date            = CURDATE(),
                Time            = CURTIME(),
                Height          = :height,
                Weight          = :weight,
                BSA             = :bsa,
                AppointmentId   = :appId,
                PatientId       = :mrn
        ")->execute([
            ":pSer"     => $patient->id,
            ":height"   => $height,
            ":weight"   => $weight,
            ":bsa"      => $bsa,
            ":appId"    => "$appointmentSourceSystem-$appointmentSourceId",
            ":mrn"      => $patient->getActiveMrns()[0]->mrn ."-". $patient->getActiveMrns()[0]->site
        ]);
    }

    public static function getLastQuestionnaireReview(Patient $patient): ?DateTime
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                MAX(ReviewTimestamp) AS LastQuestionnaireReview
            FROM
                TEMP_PatientQuestionnaireReview
            WHERE
                PatientSer = ?
        ");
        $query->execute([$patient->id]);

        $lastQuestionnaireReview = $query->fetchAll()[0]["LastQuestionnaireReview"] ?? null;

        return ($lastQuestionnaireReview === null) ? null : new DateTime($lastQuestionnaireReview);
    }

    public static function insertQuestionnaireReview(Patient $patient, string $user): void
    {
        Database::getOrmsConnection()->prepare("
            INSERT INTO TEMP_PatientQuestionnaireReview(PatientSer,User)
            VALUES(:pid,:user)
        ")->execute([
            ":pid"   => $patient->id,
            ":user"  => $user
        ]);
    }

}
