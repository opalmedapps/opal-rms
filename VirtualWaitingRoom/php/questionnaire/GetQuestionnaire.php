<?php
//php script to return questionnaire data
//was converted to a function

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

function GetQuestionnaireData($wsPatientID,$wsrptID,$wsQuestionnaireSerNum,$qstID)
{
    // Patient ID $wsPatientID
    // Report Name $wsrptID
    // Questionnaire Sequence Number $wsQuestionnaireSerNum
    // Unique report ID (QuestionSerNum in the DB)

    // Exit if either Patient ID, Report ID, or Questionnaire ID is empty
    if ( (strlen(trim($wsPatientID)) == 0) or (strlen(trim($wsrptID)) == 0) or (strlen(trim($wsQuestionnaireSerNum)) == 0) ) {
        die;
    }

    // Setup the database connection
    $dsCrossDatabase = Config::getConfigs("database")["OPAL_DB"];

    // Connect to the database
    $connection = Config::getDatabaseConnection("QUESTIONNAIRE");

    // Check datbaase connection
    if (!$connection) {
        // stop if connection failed
        echo "Failed to connect to MySQL";
        die;
    }

    $sql = "CALL getQuestionNameAndAnswerByID('$wsPatientID',$wsQuestionnaireSerNum,'$wsrptID','$dsCrossDatabase','$qstID')";

    //fetch table rows from mysql db
    /*$sql = "select
        unix_timestamp(DATE_FORMAT(PQ.DateTimeAnswered, '%Y-%m-%d')) as DateTimeAnswered,
        Q2.QuestionName, Q2.QuestionName_FR,
        A.Answer
    from PatientQuestionnaire PQ, Answer A, Questionnaire Q1, QuestionnaireQuestion QQ, Question Q2, Source S, QuestionType QT, Patient P
    where PQ.PatientQuestionnaireSerNum = A.PatientQuestionnaireSerNum
        and A.QuestionnaireQuestionSerNum = QQ.QuestionnaireQuestionSerNum
        and Q1.QuestionnaireSerNum = QQ.QuestionnaireSerNum
        and QQ.QuestionSerNum = Q2.QuestionSerNum
        and Q2.SourceSerNum = S.SourceSerNum
        and Q2.QuestionTypeSerNum = QT.QuestionTypeSerNum
        and PQ.PatientSerNum = P.PatientSerNum
        and Q1.QuestionnaireSerNum = $wsQuestionnaireSerNum
        and P.PatientId = '$wsPatientID'
        and Q2.QuestionQuestion = '$wsrptID'
    order by QQ.OrderNum asc, PQ.PatientQuestionnaireSerNum asc;"; */

    $result = $connection->query($sql) or die("Error in Selecting ");

    // Prepare the output
    $output = [];

    foreach($result->fetchAll() as $row)
    {
        // merge the output
        $output[] = [$row['DateTimeAnswered'] .'000', $row['Answer']];
    }

    // return the output
    return $output;
}

?>
