<?php declare(strict_types = 1);

namespace Orms\DataAccess;

use PDO;
use Exception;
use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Patient;

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
                    DateOfBirth         = :dob
                    OpalPatient         = :status,
                    SMSAlertNum         = :smsNum,
                    SMSSignupDate       = IF(:smsNum IS NULL,NULL,COALESCE(SMSSignupDate,NOW()))
                    SMSLastUpdated      = NOW(),
                    LanguagePreference  = :language
            ";

            $setValuesSql = [
                ":fn"           => strtoupper($patient->firstName),
                ":ln"           => strtoupper($patient->lastName),
                ":dob"          => $patient->dateOfBirth->format("Y-m-d H:i:s"),
                ":status"       => $patient->opalPatient,
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

}
