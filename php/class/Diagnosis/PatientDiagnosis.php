<?php declare(strict_types = 1);

namespace Orms\Diagnosis;

use Exception;
use PDOException;

use Orms\Database;
use Orms\DateTime;
use Orms\Diagnosis\Diagnosis;

class PatientDiagnosis
{
    public int $id;
    public int $patientId;
    public string $status;
    public DateTime $diagnosisDate;
    public DateTime $createdDate;
    public DateTime $updatedDate;
    public Diagnosis $diagnosis;

    function __construct(
        int $id,
        int $patientId,
        string $status,
        DateTime $diagnosisDate,
        DateTime $createdDate,
        DateTime $updatedDate,
        Diagnosis $diagnosis
    )
    {
        $this->id               = $id;
        $this->patientId        = $patientId;
        $this->status           = $status;
        $this->diagnosisDate    = $diagnosisDate;
        $this->createdDate      = $createdDate;
        $this->updatedDate      = $updatedDate;
        $this->diagnosis        = $diagnosis;
    }

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

    static function insertPatientDiagnosis(int $patientId,int $diagnosisSubcodeId,DateTime $diagnosisDate): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            INSERT INTO PatientDiagnosis(PatientSerNum,DiagnosisSubcodeId,DiagnosisDate,Status,UpdatedBy)
            VALUES (:pId,:dId,:dDate,'Active','SYSTEM')
        ");
        $query->execute([
            ":pId"      => $patientId,
            ":dId"      => $diagnosisSubcodeId,
            ":dDate"    => $diagnosisDate->format("Y-m-d H:i:s")
        ]);

        return (int) $dbh->lastInsertId();
    }

    static function updatePatientDiagnosis(int $patientDiagnosisId,int $diagnosisId,DateTime $diagnosisDate,string $status): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            UPDATE PatientDiagnosis
            SET
                DiagnosisSubcodeId = :codeId
                ,DiagnosisDate = :dDate
                ,Status = :status
            WHERE
                PatientDiagnosisId = :pdId
        ");
        $query->execute([
            ":pdId"     => $patientDiagnosisId,
            ":codeId"   => $diagnosisId,
            "dDate"     => $diagnosisDate->format("Y-m-d H:i:s"),
            ":status"   => $status
        ]);

        return $patientDiagnosisId;
    }


    // static function updateDiagnosis(int $patientId,int $patientDiagnosisId,int $diagnosisSubcodeId,string $status)
    // {
    //     $dbh = Config::getDatabaseConnection("ORMS");
    //     $query = $dbh->prepare("
    //         UPDATE PatientDiagnosisId PD
    //             PD.DiagnosisSubcodeId = :dId,
    //             PD.Status = :stat
    //         WHERE
    //             PD.PatientSerNum = :pId
    //             AND PD.PatientDiagnosisId = :pdId
    //     ");
    //     $query->execute([
    //         ":pId"      => $patientId,
    //         "pdId"      => $patientDiagnosisId,
    //         ":dId"      => $diagnosisSubcodeId,
    //         ":stat"     => $status
    //     ]);
    // }

}
