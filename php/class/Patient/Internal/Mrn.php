<?php declare(strict_types = 1);

namespace Orms\Patient\Internal;

use Orms\DataAccess\Database;

/** @psalm-immutable */
class Mrn
{
     function __construct(
        public string $mrn,
        public string $site,
        public bool $active
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
                (bool) $x["Active"]
            );
        },$query->fetchAll());
    }
}
