<?php declare(strict_types = 1);

namespace Orms;


use PDOException;

use Orms\Config;
use Orms\Database;
use Orms\Patient;

class Opal
{
    /////////////////////////////////////
    // direct connections to Opal db
    /////////////////////////////////////

    /**
     *
     * @return array<int,array<string,string>>
     * @throws PDOException
     */
    static function getListOfQuestionnaires(): array
    {
        $dbh = Database::getOpalConnection();

        if($dbh === NULL) return [];

        $query = $dbh->prepare("
            SELECT DISTINCT
                QuestionnaireName_EN AS Name
            FROM
                QuestionnaireControl
            ORDER BY
                QuestionnaireName_EN
        ");
        $query->execute();

        return $query->fetchAll();
    }

    /**
     * Returns an array of arrays with the following fields:
     *  - PatientId (this is the RVH mrn)
     *  - CompletionDate
     *  - Status
     *  - QuestionnaireDBSerNum
     *  - QuestionnaireName_EN
     *  - Total
     *  - Sex
     *  - Age
     *  - Visualization
     * @return array<int,array<string,string>>
     * @throws PDOException
     */
    static function getListOfQuestionnairesForPatient(Patient $patient): array
    {
        $dbh = Database::getQuestionnaireConnection();
        $opalDb = Config::getApplicationSettings()->opalDb?->databaseName;

        if($dbh === NULL || $opalDb === NULL) return [];

        //get the RVH mrn as this is all that this stored procedure currently supports
        $mrn = array_values(array_filter($patient->mrns,fn($x) => $x->site === "RVH"))[0]->mrn ?? NULL;

        $query = $dbh->prepare("CALL getQuestionnaireListORMS(?,?)");
        $query->execute([$mrn,$opalDb]);

        return $query->fetchAll();
    }

    /**
     *
     * @return mixed[]
     * @throws PDOException
     */
    static function getPatientAnswersForChartTypeQuestionnaire(Patient $patient,int $questionnaireId): array
    {
        $dbh = Database::getQuestionnaireConnection();
        $opalDb = Config::getApplicationSettings()->opalDb?->databaseName;
        $opalPatient = self::_getOpalDatabasePatient($patient);

        if($dbh === NULL || $opalDb === NULL || $opalPatient === NULL) return [];

        //fetch the questions in the questionnaire
        $queryQuestions = $dbh->prepare("CALL queryQuestions(?)");
        $queryQuestions->execute([$questionnaireId]);
        $questions = $queryQuestions->fetchAll();
        $queryQuestions->nextRowset();

        $queryAnswers = $dbh->prepare("CALL getQuestionNameAndAnswerByID(:mrn,:questionnaireId,:questionText,:opalDb,:questionId)");

        //for each question, get all answers the patient submitted for that particular question
        return array_map(function($x) use($opalPatient,$opalDb,$questionnaireId,$queryAnswers) {

            $queryAnswers->execute([
                ":mrn"              => $opalPatient["PatientId"],
                ":questionnaireId"  => $questionnaireId,
                ":questionText"     => $x["QuestionText_EN"],
                ":opalDb"           => $opalDb,
                ":questionId"       => $x["QuestionnaireQuestionSerNum"]
            ]);

            $answers = array_map(function($y) {
                return [
                    (int) $y["DateTimeAnswered"],
                    (int) $y["Answer"]
                ];
            },$queryAnswers->fetchAll());
            $queryAnswers->nextRowset();

            //sort answers by datetime answered
            usort($answers,fn($a,$b) => $a[0] <=> $b[0]);

            $questionText = ($opalPatient["Language"] === "EN") ? $x["QuestionText_EN"] : $x["QuestionText_FR"];

            return  [
                "question" => filter_var($questionText,FILTER_SANITIZE_STRING),
                "language" => $opalPatient["Language"],
                "data"  => $answers
            ];
        },$questions);
    }

    /**
     *
     * @return mixed[]
     * @throws PDOException
     */
    static function getPatientAnswersForNonChartTypeQuestionnaire(Patient $patient,int $questionnaireId): array
    {
        $dbh = Database::getQuestionnaireConnection();
        $opalPatient = self::_getOpalDatabasePatient($patient);

        if($dbh === NULL || $opalPatient === NULL) return [];

        //fetch the questions in the questionnaire
        $queryQuestions = $dbh->prepare("CALL getCompletedQuestionnaireInfo(?,?);");
        $queryQuestions->execute([$opalPatient["PatientSerNum"],$questionnaireId]);
        $questions = $queryQuestions->fetchAll();
        $queryQuestions->nextRowset();

        //attach the patient's language
        $questions = array_map(function($x) use($opalPatient) {
            $x["Language"] = $opalPatient["Language"];
            return $x;
        },$questions);

        //fetch the choices available for each question
        $queryChoices = $dbh->prepare("CALL queryQuestionChoicesORMS(?)");

        $questions = array_map(function($x) use($queryChoices) {
            $queryChoices->execute([$x["QuestionSerNum"]]);
            $x["choices"] = $queryChoices->fetchAll();
            $queryChoices->nextRowset();

            return $x;
        },$questions);

        //fetch the answers to each question
        $queryAnswers = $dbh->prepare("CALL getAnswerByAnswerQuestionnaireIdAndQuestionSectionId(?,?)");

        $questions = array_map(function($x) use($queryAnswers) {
            $queryAnswers->execute([$x["PatientQuestionnaireSerNum"],$x["QuestionnaireQuestionSerNum"]]);
            $x["answers"] = array_column($queryAnswers->fetchAll(),"Answer");
            $queryAnswers->nextRowset();

            return $x;
        },$questions);

        return $questions;
    }

    /**
     *
     * @return mixed[]
     * @throws PDOException
     */
    static function getLastCompletedPatientQuestionnaire(string $mrn,string $site): array
    {
        $dbh = Database::getOpalConnection();
        if($dbh === NULL) return [];

        $queryOpal = $dbh->prepare("
            SELECT
                Questionnaire.CompletionDate AS QuestionnaireCompletionDate,
                CASE
                    WHEN Questionnaire.CompletionDate BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND NOW() THEN 1
                    ELSE 0
                END AS CompletedWithinLastWeek
            FROM
                Patient P
                INNER JOIN Patient_Hospital_Identifier PH ON PH.PatientSerNum = P.PatientSerNum
                    AND PH.Hospital_Identifier_Type_Code = :site
                    AND PH.MRN = :mrn
                INNER JOIN Questionnaire ON Questionnaire.PatientSerNum = P.PatientSerNum
                    AND Questionnaire.CompletedFlag = 1
                    AND Questionnaire.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW()
            ORDER BY Questionnaire.CompletionDate DESC
            LIMIT 1
        ");
        $queryOpal->execute([
            ":mrn" => $mrn,
            ":site" => $site
        ]);

        return $queryOpal->fetchAll()[0] ?? [];
    }

    /**
     *
     * @return mixed[]
     * @throws PDOException
     */
    static function getLastCompletedPatientQuestionnaireClinicalViewer(string $mrn,string $questionnaireDate): array
    {
        $dbh = Database::getOpalConnection();
        if($dbh === NULL) return [];

        $query = $dbh->prepare("
            SELECT
                Q.CompletionDate AS QuestionnaireCompletionDate
                ,CASE
                    WHEN Q.CompletionDate BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND NOW() THEN 1
                    ELSE 0
                    END AS CompletedWithinLastWeek
                ,CASE
                    WHEN Q.LastUpdated BETWEEN :qDate AND NOW() THEN 1
                    ELSE 0
                    END AS RecentlyAnswered
            FROM
                Patient P
                INNER JOIN Questionnaire Q ON Q.PatientSerNum = P.PatientSerNum
                    AND Q.CompletedFlag = 1
            WHERE
                P.PatientId = :mrn
            ORDER BY
                Q.CompletionDate DESC
            LIMIT 1
        ");
        $query->execute([
            ":mrn"   => $mrn,
            ":qDate" => $questionnaireDate
        ]);

        return $query->fetchAll()[0] ?? [];
    }

    /**
     *
     * @param mixed[] $questionnaireList
     * @return mixed[]
     */
    static function getOpalPatientsAccordingToVariousFilters(array $questionnaireList,string $questionnaireDate): array
    {
        $dbh = Database::getOpalConnection();
        if($dbh === NULL) return [];

        $qappFilter = ($questionnaireList === []) ? "" : " AND QC.QuestionnaireName_EN IN ('" . implode("','",$questionnaireList) . "')";

        //if the questionnaire list is emmpty, this will return every patient in the opal database that completed a questionnaire
        $query = $dbh->prepare("
            SELECT DISTINCT
                P.PatientId,
                MAX(Q.CompletionDate) AS QuestionnaireCompletionDate,
                QC.QuestionnaireName_EN AS QuestionnaireName,
                CASE
                    WHEN Q.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW() THEN 1
                    ELSE 0
                    END AS CompletedWithinLastWeek,
                CASE
                    WHEN Q.LastUpdated BETWEEN :qDate AND NOW() THEN 1
                        ELSE 0
                    END AS RecentlyAnswered
            FROM
                Patient P
                INNER JOIN Questionnaire Q ON Q.PatientSerNum = P.PatientSerNum
                    AND Q.CompletedFlag = 1
                INNER JOIN QuestionnaireControl QC ON QC.QuestionnaireControlSerNum = Q.QuestionnaireControlSerNum
                    $qappFilter
            GROUP BY
                P.PatientId
            ORDER BY
                Q.CompletionDate DESC
        ");
        $query->execute([":qDate" => $questionnaireDate]);

        return $query->fetchAll();
    }

    /**
     *
     * @return null|mixed[]
     * @throws PDOException
     */
    private static function _getOpalDatabasePatient(Patient $patient): ?array
    {
        $dbh = Database::getOpalConnection();

        if($dbh === NULL) return NULL;

        //use the first mrn the patient has in ORMS as the the patient should have the exact same mrns in Opal
        $mrn = $patient->getActiveMrns()[0];

        $query = $dbh->prepare("
            SELECT
                P.PatientSerNum
                ,P.PatientId
                ,CONCAT(TRIM(P.FirstName),' ',TRIM(P.LastName)) AS Name
                ,LEFT(P.sex,1) as Sex
                ,P.DateOfBirth
                ,P.Age
                ,P.Language
            FROM
                Patient P
                INNER JOIN Patient_Hospital_Identifier PH ON PH.PatientSerNum = P.PatientSerNum
                    AND PH.Hospital_Identifier_Type_Code = :site
                    AND PH.MRN = :mrn
        ");
        $query->execute([
            ":site" => $mrn->mrn,
            ":mrn"  => $mrn->site
        ]);

        return $query->fetchAll()[0] ?? NULL;
    }
}

?>
