<?php declare(strict_types = 1);

namespace Orms\Patient;

use Exception;

use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Mrn;
use Orms\Patient\Insurance;

/** @psalm-immutable */
class Patient
{
    private function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        public ?string $smsNum,
        public int $opalPatient,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */ public array $mrns,
        /** @var Insurance[] $insurances */ public array $insurances
    ) {}

    /**
     * Inserts a new patient in the database. The patient must have at least one active mrn.
     * @param list<array{mrn: string,site: string,active: bool}> $mrns
     */
    static function insertNewPatient(
        string $firstName,
        string $lastName,
        DateTime $dateOfBirth,
        array $mrns
    ): self
    {
        $dbh = Database::getOrmsConnection();
        $dbh->beginTransaction();
        $dbh->prepare("
            INSERT INTO Patient
            SET
                FirstName       = :fn,
                LastName        = :ln,
                DateOfBirth     = :dob
        ")->execute([
            ":fn"           => strtoupper($firstName),
            ":ln"           => strtoupper($lastName),
            ":dob"          => $dateOfBirth->format("Y-m-d H:i:s")
        ]);

        $patient = self::getPatientById((int) $dbh->lastInsertId());
        if($patient === NULL) {
            throw new Exception("Failed to insert patient");
        }

        //make sure an active mrn is inserted first
        usort($mrns,fn($x) => ($x["active"] === TRUE) ? 0 : 1);

        foreach($mrns as $m) {
            $patient = self::updateMrn($patient,$m["mrn"],$m["site"],$m["active"]);
        }

        //verify that the patient has an active mrn
        if(count($patient->getActiveMrns()) === 0) {
            throw new Exception("Failed to create patient with no active mrns");
        }

        $dbh->commit();
        return $patient;
    }

    static function getPatientById(int $id): ?self
    {
        return self::_fetchPatient($id);
    }

    static function getPatientByMrn(string $mrn,string $site): ?self
    {
        $id = Mrn::getPatientIdForMrn($mrn,$site);
        return self::_fetchPatient($id);
    }

    static function getPatientByInsurance(string $insuranceNumber,string $type): ?self
    {
        $id = Insurance::getPatientIdForInsurance($insuranceNumber,$type);
        return self::_fetchPatient($id);
    }

    private static function _fetchPatient(int $id): ?self
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
            mrns:                  Mrn::getMrnsForPatientId($id),
            insurances:            Insurance::getInsurancesForPatientId($id),
            smsNum:                $row["SMSAlertNum"],
            opalPatient:           (int) $row["OpalPatient"],
            languagePreference:    $row["LanguagePreference"],
        );
    }

    static function updateName(self $patient,string $firstName,string $lastName): self
    {
        Database::getOrmsConnection()->prepare("
            UPDATE Patient
            SET
                FirstName = :fn,
                LastName  = :ln
            WHERE
                PatientSerNum = :id
        ")->execute([
            ":fn" => strtoupper($firstName),
            ":ln" => strtoupper($lastName),
            ":id" => $patient->id
        ]);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update name for patient $patient->id");
    }

    static function updateDateOfBirth(self $patient,DateTime $dateOfBirth): self
    {
        Database::getOrmsConnection()->prepare("
            UPDATE Patient
            SET
                DateOfBirth = :dob
            WHERE
                PatientSerNum = :id
        ")->execute([
            ":dob" => $dateOfBirth->format("Y-m-d H:i:s"),
            ":id"  => $patient->id
        ]);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update name for patient $patient->id");
    }

    static function updateOpalStatus(self $patient,int $opalStatus): self
    {
        Database::getOrmsConnection()->prepare("
            UPDATE Patient
            SET
                OpalPatient = :status
            WHERE
                PatientSerNum = :id
        ")->execute([
            ":status" => $opalStatus,
            ":id"     => $patient->id
        ]);

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update opal status for patient $patient->id");
    }

    /**
     * Updates a patient's phone number. Can also remove a patient's phone number.
     *
     */
    static function updatePhoneNumber(self $patient,?string $phoneNumber,?string $languagePreference): self
    {
        if($phoneNumber === NULL && $languagePreference === NULL)
        {
            Database::getOrmsConnection()->prepare("
                UPDATE Patient
                SET
                    SMSAlertNum = NULL,
                    SMSSignupDate = NULL,
                    SMSLastUpdated = NOW(),
                    LanguagePreference = NULL
                WHERE
                    PatientSerNum = :id
            ")->execute([
                ":id"       => $patient->id
            ]);
        }
        elseif($phoneNumber !== NULL && $languagePreference !== NULL)
        {
            //phone number must be exactly 10 digits
            if(!preg_match("/[0-9]{10}/",$phoneNumber)) throw new Exception("Invalid phone number");

            Database::getOrmsConnection()->prepare("
                UPDATE Patient
                SET
                    SMSAlertNum = :smsNum,
                    SMSSignupDate = IF(SMSSignupDate IS NULL,NOW(),SMSSignupDate),
                    SMSLastUpdated = NOW(),
                    LanguagePreference = :language
                WHERE
                    PatientSerNum = :id
            ")->execute([
                ":smsNum"   => $phoneNumber,
                ":language" => $languagePreference,
                ":id"       => $patient->id
            ]);
        }
        else {
            throw new Exception("Invalid inputs for updating phone number");
        }

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update phone number for patient $patient->id");
    }

    static function updateMrn(self $patient,string $mrn,string $site,bool $active): self
    {
        $dbh = Database::getOrmsConnection();

        $alreadyInTransaction = $dbh->inTransaction();

        if($alreadyInTransaction === FALSE) {
            $dbh->beginTransaction();
        }

        Mrn::updateMrnForPatientId($patient->id,$mrn,$site,$active);
        $patient = self::getPatientById($patient->id) ?? throw new Exception("Failed to update mrns for patient $patient->id");

        //verify that the patient has an active mrn
        if(count($patient->getActiveMrns()) === 0) {
            throw new Exception("Failed to update patient with no active mrns");
        }

        if($alreadyInTransaction === FALSE) {
            $dbh->commit();
        }

        return $patient;
    }

    static function updateInsurance(self $patient,string $insuranceNumber,string $type,DateTime $expirationDate,bool $active): self
    {
        Insurance::updateInsuranceForPatientId($patient->id,$insuranceNumber,$type,$expirationDate,$active);
        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update insurance for patient $patient->id");
    }

    /**
     * Finds all patients who have the input phone number and unregisters that phone number. Returns an array of patients who had their numbers removed
     * @return self[]
     */
    static function unregisterPhoneNumberFromPatients(string $phoneNumber): array
    {
        //phone number must be exactly 10 digits
        if(!preg_match("/[0-9]{10}/",$phoneNumber)) throw new Exception("Invalid phone number");

        //find all patient's with the phone number
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
     *
     * @return Mrn[]
     */
    function getActiveMrns(): array
    {
        $mrns = array_values(array_filter($this->mrns,fn($x) => $x->active === TRUE));

        //sort the mrns to guarentee that they're always in the same order
        usort($mrns,function($a,$b) {
            return [$a->mrn,$a->site] <=> [$b->mrn,$b->site];
        });

        return $mrns;
    }
}
