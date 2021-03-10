<?php declare(strict_types = 1);

namespace Orms\Diagnosis;

use Exception;
use PDOException;

use Orms\Database;

/** @psalm-immutable */
class Diagnosis
{
    function __construct(
        public int $id,
        public string $subcode,
        public string $subcodeDescription,
        public string $code,
        public string $codeDescription,
        public string $codeCategory,
        public string $chapter,
        public string $chapterDescription,
    ) {}

    /**
     *
     * @throws PDOException
     * @throws Exception
     */
    static function getDiagnosisFromId(int $id): self
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                DS.DiagnosisSubcodeId
                ,DS.Subcode
                ,DS.Description AS SubcodeDescription
                ,DC.Code
                ,DC.Category
                ,DC.Description AS CodeDescription
                ,DH.Chapter
                ,DH.Description AS ChapterDescription
            FROM
                DiagnosisSubcode DS
                INNER JOIN DiagnosisCode DC ON DC.DiagnosisCodeId = DS.DiagnosisCodeId
                INNER JOIN DiagnosisChapter DH ON DH.DiagnosisChapterId = DC.DiagnosisChapterId
            WHERE
                DS.DiagnosisSubcodeId = :id
        ");
        $query->execute([":id" => $id]);
        $row = $query->fetchAll()[0] ?? NULL;

        if($row === NULL) throw new Exception("Unknown diagnosis code");

        return new Diagnosis(
            (int) $row["DiagnosisSubcodeId"],
            $row["Subcode"],
            $row["SubcodeDescription"],
            $row["Code"],
            $row["Category"],
            $row["CodeDescription"],
            $row["Chapter"],
            $row["ChapterDescription"]
        );
    }

    /**
     *
     * @return Diagnosis[]
     * @throws PDOException
     */
    static function getSubcodeList(?string $filter = NULL): array
    {
        $queryFilters = "";
        $paramFilter = [];

        if($filter !== NULL)
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
                DS.DiagnosisSubcodeId
                ,DS.Subcode
                ,DS.Description AS SubcodeDescription
                ,DC.Code
                ,DC.Category
                ,DC.Description AS CodeDescription
                ,DH.Chapter
                ,DH.Description AS ChapterDescription
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
                (int) $x["DiagnosisSubcodeId"],
                $x["Subcode"],
                $x["SubcodeDescription"],
                $x["Code"],
                $x["Category"],
                $x["CodeDescription"],
                $x["Chapter"],
                $x["ChapterDescription"]
            );
        },$query->fetchAll());
    }

    /**
     *
     * @return Diagnosis[]
     * @throws PDOException
     */
    static function getUsedSubCodeList(): array
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                DS.DiagnosisSubcodeId
                ,DS.Subcode
                ,DS.Description AS SubcodeDescription
                ,DC.Code
                ,DC.Category
                ,DC.Description AS CodeDescription
                ,DH.Chapter
                ,DH.Description AS ChapterDescription
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
                (int) $x["DiagnosisSubcodeId"],
                $x["Subcode"],
                $x["SubcodeDescription"],
                $x["Code"],
                $x["Category"],
                $x["CodeDescription"],
                $x["Chapter"],
                $x["ChapterDescription"]
            );
        },$query->fetchAll());
    }

}
