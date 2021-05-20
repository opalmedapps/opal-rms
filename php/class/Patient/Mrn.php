<?php declare(strict_types = 1);

namespace Orms\Patient;

use Orms\Database;

/** @psalm-immutable */
class Mrn
{
     function __construct(
        public string $mrn,
        public string $site,
        public bool $active,
        public int $patientId
    ) {}

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
     *
     * @return self[]
     */
    static function getMrnsForPatientId(int $patientId): array
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
                (bool) $x["Active"],
                (int) $x["PatientId"]
            );
        },$query->fetchAll());
    }

    /**
     * Updates a patient's mrn by comparing it to what the patient has in the database.
     * If the mrn doesn't exist, it is inserted.
     * A patient must always have an active mrn.
     */
    static function updateMrnForPatientId(int $patientId,string $mrn,string $site,bool $active): void
    {
        // $mrn = str_pad($mrn,7,"0",STR_PAD_LEFT);
        // $ramq = preg_match("/^[a-zA-Z]{4}[0-9]{8}$/",$ramq ?? "") ? $ramq : NULL;
        $dbh = Database::getOrmsConnection();

        //check if the mrn current exists
        $queryExists = $dbh->prepare("
            SELECT
                PH.Active
            FROM
                PatientHospitalIdentifier PH
                INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
                    AND H.HospitalCode = :site
            WHERE
                PH.MedicalRecordNumber = :mrn
                AND PH.PatientId = :pid
        ");
        $queryExists->execute([
            ":site" => $site,
            ":mrn"  => $mrn,
            "pid"   => $patientId
        ]);

        $mrnActive = $queryExists->fetchAll()[0]["Active"] ?? NULL;

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
                ":active" => $active
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
                ":active" => $active
            ]);
        }
    }
}
