<?php declare(strict_types=1);
/*
rptID 11 = Patient Satisfactory Questionnaires
rptID 19 = Opal Feedback
rptID 20 = Patient Acceptibility
rptID 21 = Information about diagnosis and prognosis of cancer disease
*/

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;
use Orms\Database;

// define some constants
$wsMonthEN = "'Janurary', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'";
$wsShortMonthEN = "'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'";
$wsWeekDaysEN = "'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'";

$wsMonthFR = "'Janvier', 'F�vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao�t', 'Septembre', 'Octobre', 'Novembre', 'D�cembre'";
$wsShortMonthFR = "'jan', 'f�v', 'mar', 'avr', 'mai', 'juin', 'juil', 'ao�', 'sep', 'oct', 'nov', 'd�c'";
$wsWeekDaysFR = "'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'";

$wsResponseEN = 'Response';
$wsResponseFR = 'R�ponse';

$LastValueEN = 'Final Value';
$LastValueFR = 'TR_Final Value';

$wsNameEN = 'Name';
$wsNameFR = 'Nom';

$wsAgeEN = 'Age';
$wsAgeFR = '�ge';

$wsSexEN = 'Sex';
$wsSexFR = 'Sexe';

$wsLanguageEN = 'Language';
$wsLanguageFR = 'Langue';

$day = ["Dimanche","Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"];
$month = ["janvier", "f�vrier", "mars", "avril", "mai", "juin", "juillet", "ao�t", "septembre", "octobre", "novembre", "d�cembre"];

$wsBackgroundColor = '#33B8FF';

// Get Patient ID
// Get Patient ID
$wsPatientID = $_GET['mrn'] ?? NULL;

// Exit if Patient ID is empty
if ($wsPatientID === NULL) exit("No mrn!");

// Get Report ID
$wsReportID = $_GET['rptID'] ?? NULL;

$wsExportFlag = 0;

// Connect to the database
$connection = Database::getQuestionnaireConnection();
$connectionOpal = Database::getOpalConnection();

if($connection === NULL || $connectionOpal === NULL) exit("Failed to connect to Opal");

// Get the title of the report
$qSQLTitle = $connectionOpal->prepare("Select * from QuestionnaireControl where QuestionnaireDBSerNum = $wsReportID;");
$qSQLTitle->execute();
$rowTitle = $qSQLTitle->fetchAll()[0];

$wsReportTitleEN = $rowTitle['QuestionnaireName_EN'];
$wsReportTitleFR = $rowTitle['QuestionnaireName_FR'];

// Step 1) Retrieve Patient Information from Opal database from Patient ID ($wsPatientID)
$qSQLPI = $connectionOpal->prepare("
    select PatientSerNum, PatientID, trim(concat(trim(FirstName), ' ',trim(LastName))) as Name, left(sex, 1) as Sex, DateOfBirth, Age, Language
    from Patient
    where PatientID = $wsPatientID
");
$qSQLPI->execute();
$rowPI = $qSQLPI->fetchAll()[0];

$wsPatientSerNum = $rowPI['PatientSerNum'];

// Get the patient preferred language
$wsLanguage = $rowPI['Language'];
// $wsLanguage = 'FR'; // TEST ONLY

// Prepare the display based on the language
// EN = Enlgish Else French
if($wsLanguage === "EN")
{
    $wsResponse = $wsResponseEN;
    $wsReportTitle = $wsReportTitleEN;
    $wsName = $wsNameEN;
    $wsAge = $wsAgeEN;
    $wsSex = $wsSexEN;
}
else
{
    $wsResponse = $wsResponseFR;
    $wsReportTitle = $wsReportTitleFR;
    $wsName = $wsNameFR;
    $wsAge = $wsAgeFR;
    $wsSex = $wsSexFR;
};

// Prepare the query to retrieve the questionnaires that only has responses
$qSQLQR = $connection->prepare("CALL getCompletedQuestionnaireInfo($wsPatientSerNum, $wsReportID);");
$qSQLQR->execute();
$allRowQR = $qSQLQR->fetchAll();
$qSQLQR->closeCursor();

//the return string, will be in JSON format
$jstring = [];

//output the html as a string so that it can be parsed in the controller and then injected in the DOM

$wsPatientQuestionnaireSerNum = -1;

// begin looping the questionnaires
foreach($allRowQR as $rowQR)
{
    $jstringObj = [];

    // Display the questionnaire sequence number and the date when the patient answered
    if ($wsPatientQuestionnaireSerNum != $rowQR['PatientQuestionnaireSerNum'])
    {
        $wsPatientQuestionnaireSerNum = $rowQR['PatientQuestionnaireSerNum'];

        if ($wsLanguage == 'EN') {
            $wsDisplayDate = date_format(new DateTime($rowQR['DateTimeAnswered']), 'l jS F Y');
            $wsDisplayQuestionQuestion = $rowQR['QuestionQuestion'] ;
        } else {
            $date = explode('|', date( "w|d|n|Y",(new DateTime($rowQR['DateTimeAnswered']))->getTimestamp()));
            $wsDisplayDate = $day[(int) $date[0]] . ' ' . $date[1] . ' ' . $month[(int) $date[2]-1] . ' ' . $date[3] ;
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
    $qSQLQC = $connection->prepare("CALL queryQuestionChoicesORMS($rowQR[QuestionSerNum])");
    $qSQLQC->execute();

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
    $qSQLQC->closeCursor();

    $jstringObj["Choice"] = $wsQuestionnaireChoice;

    // display the response from the patient
    $qSQLAnswer =  $connection->prepare("CALL getAnswerByAnswerQuestionnaireIdAndQuestionSectionId($rowQR[PatientQuestionnaireSerNum],$rowQR[QuestionnaireQuestionSerNum])");
    $qSQLAnswer->execute();

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
    $qSQLAnswer->closeCursor();

    $jstringObj["Answer"] = $wsAnswer;

    $jstring[] = $jstringObj;

};


$jstring = utf8_encode_recursive($jstring);
echo json_encode($jstring);

?>
