<?php declare(strict_types = 1);

namespace Orms\Patient;

use Orms\Database;
use PDOException;

/** @psalm-immutable */
class Mrn
{
    private function __construct(
        public string $mrn,
        public string $site,
        public bool $active
    ) {}

    static function getPatientIdForMrn(string $mrn,string $site): ?int
    {
        $query = Database::getOrmsConnection()->prepare("

        ");
        $query->execute([

        ]);

        return $query->fetchAll()[0]["PatientSerNum"] ?? NULL;
    }

    /**
     *
     * @return Mrn[]
     */
    static function getMrnsForPatientId(int $patientId): array
    {
        $query = Database::getOrmsConnection()->prepare("

        ");
        $query->execute([

        ]);

        return array_map(function($x) {
            return new Mrn($x["MedicalRecordNumber"],$x["HospitalCode"],(bool) $x["Active"]);
        },$query->fetchAll());
    }
}



?>
