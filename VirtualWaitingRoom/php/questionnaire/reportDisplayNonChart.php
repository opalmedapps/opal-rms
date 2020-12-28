<?php
/*
rptID 11 = Patient Satisfactory Questionnaires
rptID 19 = Opal Feedback
rptID 20 = Patient Acceptibility
rptID 21 = Information about diagnosis and prognosis of cancer disease
*/

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

include('LanguageFile.php');

$wsBackgroundColor = '#33B8FF';

// Get Patient ID
$wsPatientID = filter_var($_GET['mrn'], FILTER_SANITIZE_STRING);
// Exit if Patient ID is empty
if (strlen(trim($wsPatientID)) == 0) {
    die;
}

// Get Report ID
$wsReportID = filter_var($_GET['rptID'], FILTER_SANITIZE_STRING);
// Exit if Report ID is empty
if (strlen(trim($wsReportID)) == 0){
    die;
}

// Get Export Flag
//$wsExportFlag = filter_var($_GET['efID'], FILTER_SANITIZE_STRING);
$wsExportFlag = 0;
// Set value to 0 if Export Flag is empty
if (strlen(trim($wsExportFlag)) == 0) {
    $wsExportFlag=0;
}

// Setup the database connection
$dsCrossDatabse = Config::getConfigs("database")["OPAL_DB"];

// Connect to the database
$connection = Config::getDatabaseConnection("QUESTIONNAIRE");

// Check datbaase connection
if (!$connection) {
    // stop if connection failed
    echo "Failed to connect to MySQL";
    die;
}

// Get the title of the report
$qSQLTitle = $connection->query("Select * from $dsCrossDatabse.QuestionnaireControl where QuestionnaireDBSerNum = $wsReportID;");
$rowTitle = $qSQLTitle->fetchAll()[0];

$wsReportTitleEN = $rowTitle['QuestionnaireName_EN'];
$wsReportTitleFR = $rowTitle['QuestionnaireName_FR'];

// Step 1) Retrieve Patient Information from Opal database from Patient ID ($wsPatientID)
$wsSQLPI = "select PatientSerNum, PatientID, trim(concat(trim(FirstName), ' ',trim(LastName))) as Name, left(sex, 1) as Sex, DateOfBirth, Age, Language
            from " . $dsCrossDatabse . ".Patient
            where PatientID = $wsPatientID";
$qSQLPI = $connection->query($wsSQLPI);
$rowPI = $qSQLPI->fetchAll()[0];

$wsPatientSerNum = $rowPI['PatientSerNum'];

// Get the patient preferred language
$wsLanguage = $rowPI['Language'];
// $wsLanguage = 'FR'; // TEST ONLY

// Prepare the display based on the language
// EN = Enlgish Else French
if ($wsLanguage == 'EN') {
    $wsResponse = $wsResponseEN;
    $wsReportTitle = $wsReportTitleEN;
    $wsName = $wsNameEN;
    $wsAge = $wsAgeEN;
    $wsSex = $wsSexEN;
} else {
    $wsResponse = $wsResponseFR;
    $wsReportTitle = $wsReportTitleFR;
    $wsName = $wsNameFR;
    $wsAge = $wsAgeFR;
    $wsSex = $wsSexFR;
};

// Prepare the query to retrieve the questionnaires that only has responses
/*$wsSQLQR = "select PQ.PatientQuestionnaireSerNum, PQ.PatientSerNum, DATE_FORMAT(PQ.DateTimeAnswered, '%Y-%m-%d') as DateTimeAnswered, PQ.DateTimeAnswered as FullDateTimeAnswered,
        QQ.QuestionnaireQuestionSerNum, Q1.QuestionnaireSerNum, Q1.QuestionnaireName, Q2.QuestionSerNum, Q2.QuestionName, Q2.QuestionName_FR, Q2.QuestionQuestion,
        Q2.QuestionQuestion_FR,
        Q2.QuestionTypeSerNum
    from PatientQuestionnaire PQ, Patient P, Questionnaire Q1, QuestionnaireQuestion QQ, Question Q2
    where P.PatientId = '$wsPatientID'
        and Q1.QuestionnaireSerNum = $wsReportID
        and P.PatientSerNum = PQ.PatientSerNum
        and PQ.QuestionnaireSerNum = Q1.QuestionnaireSerNum
        and Q1.QuestionnaireSerNum = QQ.QuestionnaireSerNum
        and QQ.QuestionSerNum = Q2.QuestionSerNum
        and QQ.QuestionnaireQuestionSerNum in
            (select distinct QuestionnaireQuestionSerNum
            from Answer where PatientSerNum = P.PatientSerNum
                and PatientQuestionnaireSerNum = PQ.PatientQuestionnaireSerNum
            )
    order by PQ.PatientQuestionnaireSerNum desc, QQ.OrderNum asc;
";
$qSQLQR = $connection->query($wsSQLQR);*/
$wsSQLQR = "CALL getCompletedQuestionnaireInfo($wsPatientSerNum, $wsReportID);";
$qSQLQR = $connection->query($wsSQLQR);
$allRowQR = $qSQLQR->fetchAll();
$qSQLQR->closeCursor();

//the return string, will be in JSON format
$jstring = [];

//output the html as a string so that it can be parsed in the controller and then injected in the DOM

$wsPatientQuestionnaireSerNum = -1;

if($qSQLQR)
{
    // begin looping the questionnaires
    foreach($allRowQR as $rowQR)
    {
        $jstringObj = [];

        // Display the questionnaire sequence number and the date when the patient answered
        if ($wsPatientQuestionnaireSerNum != $rowQR['PatientQuestionnaireSerNum'])
        {
            $wsPatientQuestionnaireSerNum = $rowQR['PatientQuestionnaireSerNum'];

            if ($wsLanguage == 'EN') {
                $wsDisplayDate = date_format(date_create($rowQR['DateTimeAnswered']), 'l jS F Y');
                $wsDisplayQuestionQuestion = $rowQR['QuestionQuestion'] ;
            } else {
                $date = explode('|', date( "w|d|n|Y", strtotime($rowQR['DateTimeAnswered']) ));
                $wsDisplayDate = $day[$date[0]] . ' ' . $date[1] . ' ' . $month[$date[2]-1] . ' ' . $date[3] ;
                $wsDisplayQuestionQuestion = $rowQR['QuestionQuestion_FR'] ;
            };

                $jstringObj["DisplayDate"] = $wsDisplayDate;
        };

        // Display the question based on the language
        if ($wsLanguage == 'EN') {
            $wsDisplayQuestionQuestion = $rowQR['QuestionQuestion'] ;
        } else {
            $wsDisplayQuestionQuestion = $rowQR['QuestionQuestion_FR'] ;
        };

        $wsDisplayQuestionQuestion = str_replace("<br />","\\n",$wsDisplayQuestionQuestion);

        $wsDisplayQuestionQuestion = addslashes($wsDisplayQuestionQuestion);
        $jstringObj["Description"] = $wsDisplayQuestionQuestion;

        // What kind of choices did the patient have
        /*$wsSQLQC = "select ChoiceSerNum, ChoiceDescription
                    from QuestionChoice
                    where QuestionSerNum = " . $rowQR['QuestionSerNum'] .
                    " and QuestionTypeSerNum = " . $rowQR['QuestionTypeSerNum'] .
                    " order by QuestionChoiceSerNum";

        $qSQLQC = $connection->query($wsSQLQC);*/
        $qSQLQC = $connection->query("CALL queryQuestionChoicesORMS($rowQR[QuestionSerNum])");

        $wsQuestionnaireChoice = '';

        // The patient have a choice of Min To Max
        if ($rowQR['QuestionTypeSerNum'] == 2) {
            // Generate user choice for min to max
            foreach($qSQLQC->fetchAll() as $rowQC) {
                // In theory, there should only be two rows
                // First row is the minimum value
                if (strlen(trim($wsQuestionnaireChoice)) == 0) {
                    $wsQuestionnaireChoice = $rowQC['ChoiceSerNum'] . ' ' . trim($rowQC['ChoiceDescription']) . str_repeat(" ", 4) . str_repeat(" - ", 15) . str_repeat(" ", 4);
                } else {
                    // Second Row is the maximum value
                    $wsQuestionnaireChoice = $wsQuestionnaireChoice . $rowQC['ChoiceSerNum'] . ' ' . $rowQC['ChoiceDescription'];
                };
            };
        };
        if($qSQLQC) {$qSQLQC->closeCursor();}

        $jstringObj["Choice"] = $wsQuestionnaireChoice;

        // display the response from the patient
        /*$wsSQLAnswer = "Select Answer from Answer A
            where A.QuestionnaireQuestionSerNum = " . $rowQR['QuestionnaireQuestionSerNum'] . "
                and A.PatientQuestionnaireSerNum = " . $rowQR['PatientQuestionnaireSerNum'] . "
            order by AnswerSerNum;";
        $qSQLAnswer = $connection->query($wsSQLAnswer);*/
        $qSQLAnswer =  $connection->query("CALL getAnswerByAnswerQuestionnaireIdAndQuestionSectionId($rowQR[PatientQuestionnaireSerNum],$rowQR[QuestionnaireQuestionSerNum])");

        $wsAnswer = "";

        // loop the response for multiple choices
        foreach($qSQLAnswer->fetchAll() as $rowAnswers)
        {
            // Add comma if there are more than one answer
            if (strlen($wsAnswer) == 0) {
                $wsAnswer = "";
            } else {
                $wsAnswer = $wsAnswer . ", ";
            };

            $wsAnswer = $wsAnswer . $rowAnswers['Answer'];
        };
        if($qSQLAnswer) {$qSQLAnswer->closeCursor();}

        $jstringObj["Answer"] = $wsAnswer;

        $jstring[] = $jstringObj;

    };

}

$jstring = utf8_encode_recursive($jstring);
echo json_encode($jstring);

?>
