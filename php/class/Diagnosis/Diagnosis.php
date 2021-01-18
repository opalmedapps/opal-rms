<?php declare(strict_types = 1);

namespace Orms\Diagnosis;

use Exception;
use PDOException;

use Orms\Config;

class Diagnosis
{
    public int $id;
    public string $subcode;
    public string $subcodeDescription;
    public string $code;
    public string $codeDescription;
    public string $codeCategory;
    public string $chapter;
    public string $chapterDescription;

    function __construct(
        int $id,
        string $subcode,
        string $subcodeDescription,
        string $code,
        string $codeDescription,
        string $codeCategory,
        string $chapter,
        string $chapterDescription
    )
    {
        $this->id                       = $id;
        $this->subcode                  = $subcode;
        $this->subcodeDescription       = $subcodeDescription;
        $this->code                     = $code;
        $this->codeDescription          = $codeDescription;
        $this->codeCategory             = $codeCategory;
        $this->chapter                  = $chapter;
        $this->chapterDescription       = $chapterDescription;
    }

    /**
     *
     * @param int $id
     * @return Diagnosis
     * @throws PDOException
     * @throws Exception
     */
    static function getDiagnosisFromId(int $id): Diagnosis
    {
        $dbh = Config::getDatabaseConnection("ORMS");
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
     * @return array<string,array<string,Diagnosis>>
     * @throws PDOException
     */
    static function getSubcodeList(): array
    {
        $dbh = Config::getDatabaseConnection("ORMS");
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
