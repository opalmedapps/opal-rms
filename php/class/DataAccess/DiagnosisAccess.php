<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Exception;
use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\Diagnosis\Model\Diagnosis;
use Orms\Diagnosis\Model\PatientDiagnosis;

class DiagnosisAccess
{
    /**
     *
     * @throws Exception
     */
    public static function getDiagnosisFromId(int $id): Diagnosis
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                DS.DiagnosisSubcodeId,
                DS.Subcode,
                DS.Description AS SubcodeDescription,
                DC.Code,
                DC.Category,
                DC.Description AS CodeDescription,
                DH.Chapter,
                DH.Description AS ChapterDescription
            FROM
                DiagnosisSubcode DS
                INNER JOIN DiagnosisCode DC ON DC.DiagnosisCodeId = DS.DiagnosisCodeId
                INNER JOIN DiagnosisChapter DH ON DH.DiagnosisChapterId = DC.DiagnosisChapterId
            WHERE
                DS.DiagnosisSubcodeId = :id
        ");
        $query->execute([":id" => $id]);
        $row = $query->fetchAll()[0] ?? null;

        if($row === null) throw new Exception("Unknown diagnosis code");

        return new Diagnosis(
            id:                     (int) $row["DiagnosisSubcodeId"],
            subcode:                 $row["Subcode"],
            subcodeDescription:      $row["SubcodeDescription"],
            code:                    $row["Code"],
            codeDescription:         $row["Category"],
            codeCategory:            $row["CodeDescription"],
            chapter:                 $row["Chapter"],
            chapterDescription:      $row["ChapterDescription"],
        );
    }

    /**
     *
     * @return Diagnosis[]
     */
    public static function getSubcodeList(?string $filter = null): array
    {
        $queryFilters = "";
        $paramFilter = [];

        if($filter !== null)
        {
            $queryFilters = "
                AND (
                    DS.Subcode LIKE :filter
                    OR DS.Description LIKE :filter
                )
            ";
            $paramFilter = [":filter" => "%$filter%"];
        }

        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                DS.DiagnosisSubcodeId,
                DS.Subcode,
                DS.Description AS SubcodeDescription,
                DC.Code,
                DC.Category,
                DC.Description AS CodeDescription,
                DH.Chapter,
                DH.Description AS ChapterDescription
            FROM
                DiagnosisSubcode DS
                INNER JOIN DiagnosisCode DC ON DC.DiagnosisCodeId = DS.DiagnosisCodeId
                INNER JOIN DiagnosisChapter DH ON DH.DiagnosisChapterId = DC.DiagnosisChapterId
                    $queryFilters
            ORDER BY
                DS.Subcode
        ");
        $query->execute($paramFilter);

        return array_map(function($x) {
            return new Diagnosis(
                id:                     (int) $x["DiagnosisSubcodeId"],
                subcode:                 $x["Subcode"],
                subcodeDescription:      $x["SubcodeDescription"],
                code:                    $x["Code"],
                codeDescription:         $x["Category"],
                codeCategory:            $x["CodeDescription"],
                chapter:                 $x["Chapter"],
                chapterDescription:      $x["ChapterDescription"],
            );
        }, $query->fetchAll());
    }

    /**
     *
     * @return Diagnosis[]
     */
    public static function getUsedSubCodeList(): array
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                DS.DiagnosisSubcodeId,
                DS.Subcode,
                DS.Description AS SubcodeDescription,
                DC.Code,
                DC.Category,
                DC.Description AS CodeDescription,
                DH.Chapter,
                DH.Description AS ChapterDescription
            FROM
                PatientDiagnosis PD
                INNER JOIN DiagnosisSubcode DS ON DS.DiagnosisSubcodeId = PD.DiagnosisSubcodeId
                INNER JOIN DiagnosisCode DC ON DC.DiagnosisCodeId = DS.DiagnosisCodeId
                INNER JOIN DiagnosisChapter DH ON DH.DiagnosisChapterId = DC.DiagnosisChapterId
            ORDER BY
                DS.Subcode
        ");
        $query->execute();

        return array_map(function($x) {
            return new Diagnosis(
                id:                     (int) $x["DiagnosisSubcodeId"],
                subcode:                 $x["Subcode"],
                subcodeDescription:      $x["SubcodeDescription"],
                code:                    $x["Code"],
                codeDescription:         $x["Category"],
                codeCategory:            $x["CodeDescription"],
                chapter:                 $x["Chapter"],
                chapterDescription:      $x["ChapterDescription"],
            );
        }, $query->fetchAll());
    }

    /**
     *
     * @return PatientDiagnosis[]
     */
    public static function getDiagnosisListForPatient(int $patientId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PatientDiagnosisId,
                PatientSerNum,
                DiagnosisSubcodeId,
                Status,
                DiagnosisDate,
                CreatedDate,
                LastUpdated
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
                self::getDiagnosisFromId((int) $x["DiagnosisSubcodeId"])
            );
        }, $query->fetchAll());
    }

    public static function getDiagnosisById(int $id): PatientDiagnosis
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PatientDiagnosisId,
                PatientSerNum,
                DiagnosisSubcodeId,
                DiagnosisDate,
                Status,
                CreatedDate,
                LastUpdated
            FROM
                PatientDiagnosis
            WHERE
                PatientDiagnosisId = :id
            ORDER BY
                CreatedDate DESC
        ");
        $query->execute([":id" => $id]);

        $row = $query->fetchAll()[0] ?? null;

        if($row === null) throw new Exception("Unknown patient diagnosis code");

        return new PatientDiagnosis(
            (int) $row["PatientDiagnosisId"],
            (int) $row["PatientSerNum"],
            $row["Status"],
            new DateTime($row["DiagnosisDate"]),
            new DateTime($row["CreatedDate"]),
            new DateTime($row["LastUpdated"]),
            self::getDiagnosisFromId((int) $row["DiagnosisSubcodeId"])
        );
    }

    public static function insertPatientDiagnosis(int $patientId, string $mrn, string $site, int $diagnosisSubcodeId, DateTime $diagnosisDate, string $user): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            INSERT INTO PatientDiagnosis(
                PatientSerNum,
                RecordedMrn,
                DiagnosisSubcodeId,
                DiagnosisDate,
                Status,
                UpdatedBy
            )
            VALUES(
                :pId,
                :mrn,
                :dId,
                :dDate,
                'Active',
                :user
            )
        ");
        $query->execute([
            ":pId"      => $patientId,
            ":mrn"      => "$site-$mrn",
            ":dId"      => $diagnosisSubcodeId,
            ":dDate"    => $diagnosisDate->format("Y-m-d H:i:s"),
            ":user"     => $user
        ]);

        return (int) $dbh->lastInsertId();
    }

    public static function updatePatientDiagnosis(int $patientDiagnosisId, int $diagnosisId, DateTime $diagnosisDate, string $status, string $user): int
    {
        $query = Database::getOrmsConnection()->prepare("
            UPDATE PatientDiagnosis
            SET
                DiagnosisSubcodeId = :codeId,
                DiagnosisDate      = :dDate,
                Status             = :status,
                UpdatedBy          = :user
            WHERE
                PatientDiagnosisId = :pdId
        ");
        $query->execute([
            ":pdId"     => $patientDiagnosisId,
            ":codeId"   => $diagnosisId,
            ":dDate"    => $diagnosisDate->format("Y-m-d H:i:s"),
            ":status"   => $status,
            ":user"     => $user
        ]);

        return $patientDiagnosisId;
    }
}
