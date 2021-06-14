<?php declare(strict_types = 1);

namespace Orms\Patient\Internal;

use Exception;
use Orms\Database;
use Orms\DateTime;

/** @psalm-immutable */
class Insurance
{
    private function __construct(
        public string $number,
        public DateTime $expiration,
        public string $type,
        public bool $active,
        public int $patientId
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
                (bool) $x["Active"],
                (int) $x["PatientId"]
            );
        },$query->fetchAll());
    }

    /**
     * Updates a patient's insurance by comparing it to what the patient has in the database.
     * If the insurance doesn't exist, it is inserted
     */
    static function updateInsuranceForPatientId(int $patientId,string $insuranceNumber,string $insuranceType,DateTime $expirationDate,bool $active): void
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
