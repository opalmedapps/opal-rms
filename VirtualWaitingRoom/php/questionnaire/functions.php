<?php

// **************************************************
// ***** Write to a log file
// **************************************************
#function debuglog($wsTxt) {
#  $myfile = file_put_contents('debug.log', $wsTxt.PHP_EOL , FILE_APPEND | LOCK_EX);
#}

// **************************************************
// ***** Get the questions from the questionnaires
// **************************************************
function GetQuestionnaire($wsPatientID, $wsrptID, $wsQuestionnaireSerNum)
{
    // Get Patient ID
    $wsPatientID = filter_var($wsPatientID, FILTER_SANITIZE_STRING);

    // Get Report Name
    $wsrptID = filter_var($wsrptID, FILTER_SANITIZE_STRING);

    // Get Questionnaire Sequence Number
    $wsQuestionnaireSerNum = filter_var($wsQuestionnaireSerNum, FILTER_SANITIZE_STRING);

    // Exit if either Patient ID, Report ID, or Questionnaire ID is empty
    if ( (strlen(trim($wsPatientID)) == 0) or (strlen(trim($wsrptID)) == 0) or (strlen(trim($wsQuestionnaireSerNum)) == 0) ) {
        die;
    }

    // Setup the database connection
    require_once __DIR__."/../loadConfigs.php";

    // Connect to the database
    $connection = new PDO(QUESTIONNIARE_CONNECT,QUESTIONNAIRE_USERNAME,QUESTIONNAIRE_PASSWORD,$QUESTIONNAIRE_OPTIONS);

    // Check datbaase connection
    if (!$connection) {
        // stop if connection failed
        echo "Failed to connect to MySQL ";
        die;
    }

    $sql = "CALL getQuestionNameAndAnswer('$wsPatientID',$wsQuestionnaireSerNum,'$wsrptID','$dsCrossDatabase')";


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
    order by QQ.OrderNum asc, PQ.PatientQuestionnaireSerNum asc;";*/

    $result = $connection->query($sql) or die("Error in Selecting " .$connection->errstr);

    // Prepare the output
    $output = '';

    while($row = $result->fetch(PDO::FETCH_ASSOC))
    {
        // only add a comma if the output contain something
        if (strlen($output) > 0) {
            $output = $output . ', ';
        }

        // merge the output
        $output = $output . '[' . $row['DateTimeAnswered'] . '000,' . $row['Answer'] . ']';
    }

    // echo the output
    // echo '[' . $output . ']';
    return '[' . $output . ']';
}

?>
