<?php declare(strict_types = 1);

namespace Orms\Diagnosis\Internal;

use Exception;
use PDOException;

use Orms\Database;
use Orms\DateTime;
use Orms\Diagnosis\Internal\Diagnosis;

/** @psalm-immutable */
class PatientDiagnosis
{
    function __construct(
        public int $id,
        public int $patientId,
        public string $status,
        public DateTime $diagnosisDate,
        public DateTime $createdDate,
        public DateTime $updatedDate,
        public Diagnosis $diagnosis,
    ) {}

    /**
     *
     * @param int $patientId
     * @return PatientDiagnosis[]
     * @throws PDOException
     */
    static function getDiagnosisListForPatient(int $patientId): array
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                PatientDiagnosisId
                ,PatientSerNum
                ,DiagnosisSubcodeId
                ,Status
                ,DiagnosisDate
                ,CreatedDate
                ,LastUpdated
            FROM
                PatientDiagnosis
            WHERE
                PatientSerNum = :pid
                AND Status = 'Active'
            ORDER BY
                DiagnosisDate DESC
        ");
        $query->execute([":pid" => $patientId]);

        return array_map(function(array $x) {
            return new PatientDiagnosis(
                (int) $x["PatientDiagnosisId"],
                (int) $x["PatientSerNum"],
                $x["Status"],
                new DateTime($x["DiagnosisDate"]),
                new DateTime($x["CreatedDate"]),
                new DateTime($x["LastUpdated"]),
                Diagnosis::getDiagnosisFromId((int) $x["DiagnosisSubcodeId"])
            );
        },$query->fetchAll());
    }

    static function getDiagnosisById(int $id): PatientDiagnosis
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                PatientDiagnosisId
                ,PatientSerNum
                ,DiagnosisSubcodeId
                ,DiagnosisDate
                ,Status
                ,CreatedDate
                ,LastUpdated
            FROM
                PatientDiagnosis
            WHERE
                PatientDiagnosisId = :id
            ORDER BY
                CreatedDate DESC
        ");
        $query->execute([":id" => $id]);

        $row = $query->fetchAll()[0] ?? NULL;

        if($row === NULL) throw new Exception("Unknown patient diagnosis code");

        return new PatientDiagnosis(
            (int) $row["PatientDiagnosisId"],
            (int) $row["PatientSerNum"],
            $row["Status"],
            new DateTime($row["DiagnosisDate"]),
            new DateTime($row["CreatedDate"]),
            new DateTime($row["LastUpdated"]),
            Diagnosis::getDiagnosisFromId((int) $row["DiagnosisSubcodeId"])
        );
    }

    static function insertPatientDiagnosis(int $patientId,int $diagnosisSubcodeId,DateTime $diagnosisDate,string $user): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            INSERT INTO PatientDiagnosis(PatientSerNum,DiagnosisSubcodeId,DiagnosisDate,Status,UpdatedBy)
            VALUES (:pId,:dId,:dDate,'Active',:user)
        ");
        $query->execute([
            ":pId"      => $patientId,
            ":dId"      => $diagnosisSubcodeId,
            ":dDate"    => $diagnosisDate->format("Y-m-d H:i:s"),
            ":user"     => $user
        ]);

        return (int) $dbh->lastInsertId();
    }

    static function updatePatientDiagnosis(int $patientDiagnosisId,int $diagnosisId,DateTime $diagnosisDate,string $status,string $user): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            UPDATE PatientDiagnosis
            SET
                DiagnosisSubcodeId = :codeId
                ,DiagnosisDate = :dDate
                ,Status = :status
                ,UpdatedBy = :user
            WHERE
                PatientDiagnosisId = :pdId
        ");
        $query->execute([
            ":pdId"     => $patientDiagnosisId,
            ":codeId"   => $diagnosisId,
            "dDate"     => $diagnosisDate->format("Y-m-d H:i:s"),
            ":status"   => $status,
            ":user"     => $user
        ]);

        return $patientDiagnosisId;
    }
}
