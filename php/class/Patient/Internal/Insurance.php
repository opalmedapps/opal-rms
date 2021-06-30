<?php declare(strict_types = 1);

namespace Orms\Patient\Internal;

use Orms\DataAccess\Database;
use Orms\DateTime;

/** @psalm-immutable */
class Insurance
{
    function __construct(
        public string $number,
        public DateTime $expiration,
        public string $type,
        public bool $active
    ) {}

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
    static function getInsurancesForPatientId(int $patientId): array
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
}
