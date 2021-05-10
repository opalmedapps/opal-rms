<?php declare(strict_types = 1);

namespace Orms;

use Exception;

use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Mrn;
use Orms\Patient\Insurance;
use PDOException;

/** @psalm-immutable */
class Patient
{
    private function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public ?string $smsNum,
        public int $opalPatient,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */ public array $mrns,
        /** @var Insurance[] $insurances */ public array $insurances
    ) {}

    /**
     * Inserts a new patient in the database. The patient must have at least one mrn.
     *
     */
    static function insertNewPatient(
        string $firstName,
        string $lastName,
        string $mrn,
        string $site,
        bool $mrnStatus
    ): self
    {
        $dbh = Database::getOrmsConnection();
        $dbh->beginTransaction();
        $dbh->prepare("
            INSERT INTO Patient
            SET
                FirstName       = :fn,
                LastName        = :ln
        ")->execute([
            ":fn"           => strtoupper($firstName),
            ":ln"           => strtoupper($lastName)
        ]);

        $patient = self::getPatientById((int) $dbh->lastInsertId());
        if($patient === NULL) {
            throw new Exception("Failed to insert patient with mrn $mrn and site $site");
        }

        $patient = $patient->updateMrn($mrn,$site,$mrnStatus);

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
                PatientId,
                SMSAlertNum,
                SMSSignupDate,
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
            mrns:                  Mrn::getMrnsForPatientId($id),
            insurances:            Insurance::getInsurancesForPatientId($id),
            smsNum:                $row["SMSAlertNum"],
            opalPatient:           (int) $row["OpalPatient"],
            languagePreference:    $row["LanguagePreference"],
        );
    }

    function updateName(string $firstName,string $lastName): self
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
            ":id" => $this->id
        ]);

        return self::getPatientById($this->id) ?? throw new Exception("Failed to update name for patient $this->id");
    }

    function updateOpalStatus(int $opalStatus): self
    {
        Database::getOrmsConnection()->prepare("
            UPDATE Patient
            SET
                OpalPatient = :status
            WHERE
                PatientSerNum = :id
        ")->execute([
            ":status" => $opalStatus,
            ":id"     => $this->id
        ]);

        return self::getPatientById($this->id) ?? throw new Exception("Failed to update opal status");
    }

    function updateMrn(string $mrn,string $site,bool $active): self
    {
        Mrn::updateMrnForPatientId($this->id,$mrn,$site,$active);
        return self::getPatientById($this->id) ?? throw new Exception("Failed to update mrns for patient $this->id");
    }

    function updateInsurance(string $insuranceNumber,string $type,DateTime $expirationDate,bool $active): self
    {
        Insurance::updateInsuranceForPatientId($this->id,$insuranceNumber,$type,$expirationDate,$active);
        return self::getPatientById($this->id) ?? throw new Exception("Failed to update insurance for patient $this->id");
    }
}

?>
