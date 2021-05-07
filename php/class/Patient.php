<?php declare(strict_types = 1);

namespace Orms;

use Exception;

use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Mrn;

/** @psalm-immutable */
class Patient
{
    private function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $ramq,
        public ?DateTime $ramqExpirationDate,
        public ?string $smsNum,
        public int $opalPatient,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */ public array $mrns
    ) {}

    /*a few rules for inserting and updating patients:
        * if the patient has no ramq, use the mrn as the ramq (set the ramq to expired)
        * uppercase all names and ramqs
        * all mrns are exactly 7 digits (zero-pad those that aren't)
        * all ramqs have this format: XXXXYYMMDD
    */
    static function insertNewPatient(
        string $firstName,
        string $lastName,
        string $mrn,
        ?string $ramq,
        ?DateTime $ramqExpiration
    ): self
    {
        $mrn = str_pad($mrn,7,"0",STR_PAD_LEFT);
        $ramq = preg_match("/^[a-zA-Z]{4}[0-9]{8}$/",$ramq ?? "") ? $ramq : NULL;

        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO Patient
            SET
                FirstName       = :fn,
                LastName        = :ln,
                SSN             = :ssn,
                SSNExpDate      = :ssnExpDate,
                PatientId       = :mrn
        ")->execute([
            ":fn"           => strtoupper($firstName),
            ":ln"           => strtoupper($lastName),
            ":ssn"          => strtoupper($ramq ?? $mrn),
            ":ssnExpDate"   => $ramqExpiration?->format("ym") ?? 0,
            ":mrn"          => $mrn
        ]);

        $patient = self::getPatientById((int) $dbh->lastInsertId()) ?? throw new Exception("Failed to insert patient with mrn $mrn");
        return $patient;
    }

    static function updateDemographics(
        int $id,
        string $firstName,
        string $lastName,
        string $mrn,
        ?string $ramq,
        ?DateTime $ramqExpiration
    ): self
    {
        $mrn = str_pad($mrn,7,"0",STR_PAD_LEFT);
        $ramq = preg_match("/^[a-zA-Z]{4}[0-9]{8}$/",$ramq ?? "") ? $ramq : NULL;

        Database::getOrmsConnection()->prepare("
            UPDATE Patient
            SET
                FirstName       = :fn,
                LastName        = :ln,
                SSN             = :ssn,
                SSNExpDate      = :ssnExpDate,
                PatientId       = :mrn
            WHERE
                PatientSerNum = :id
        ")->execute([
            ":fn"           => strtoupper($firstName),
            ":ln"           => strtoupper($lastName),
            ":ssn"          => strtoupper($ramq ?? $mrn),
            ":ssnExpDate"   => $ramqExpiration?->format("ym") ?? 0,
            ":mrn"          => $mrn,
            ":id"           => $id
        ]);

        $patient = self::getPatientByMrn($mrn) ?? throw new Exception("Failed to update patient with mrn $mrn");
        return $patient;
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

        return self::getPatientById($patient->id) ?? throw new Exception("Failed to update opal status");
    }

    static function getPatientById(int $id): ?self
    {
        return self::_fetchPatient($id);
    }

    static function getPatientByMrn(string $mrn,string $site): ?self
    {
        return self::_fetchPatient($mrn);
    }

    private static function _fetchPatient(int|string $identifier): ?self
    {
        $column = match(gettype($identifier)) {
            "integer" => "PatientSerNum",
            "string"  => "PatientId",
            default   => "PatientSerNum"
        };

        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                PatientSerNum,
                LastName,
                FirstName,
                SSN,
                SSNExpDate,
                PatientId,
                SMSAlertNum,
                SMSSignupDate,
                OpalPatient,
                LanguagePreference
            FROM
                Patient
            WHERE
                $column = :iden
        ");
        $query->execute([
            ":iden" => $identifier
        ]);

        $row = $query->fetchAll()[0] ?? NULL;

        if($row === NULL) return NULL;

        $expirationDate = ($row["SSNExpDate"] === "0") ? NULL : DateTime::createFromFormatN("ym",$row["SSNExpDate"])?->modifyN("first day of")?->modifyN("midnight");

        return new Patient(
            id:                    (int) $row["PatientSerNum"],
            firstName:             $row["FirstName"],
            lastName:              $row["LastName"],
            ramq:                  $row["SSN"],
            ramqExpirationDate:    $expirationDate,
            mrn:                   $row["PatientId"],
            smsNum:                $row["SMSAlertNum"],
            opalPatient:           (int) $row["OpalPatient"],
            languagePreference:    $row["LanguagePreference"],
        );
    }
}

?>
