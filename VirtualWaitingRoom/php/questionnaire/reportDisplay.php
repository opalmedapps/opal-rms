<?php

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

include('LanguageFile.php');

// Get Patient ID
$wsPatientID = filter_var($_GET['mrn'], FILTER_SANITIZE_STRING);

// Exit if Patient ID is empty
if (strlen(trim($wsPatientID)) == 0)
{die;}

// Get Report ID
$wsReportID = filter_var($_GET['rptID'], FILTER_SANITIZE_STRING);

// Exit if Report ID is empty
if (strlen(trim($wsReportID)) == 0)
{die;}

// Get Export Flag
//$wsExportFlag = filter_var($_GET['efID'], FILTER_SANITIZE_STRING);
$wsExportFlag = 0;

// Set value to 0 if Export Flag is empty
if (strlen(trim($wsExportFlag)) == 0)
{$wsExportFlag=0;}

// Setup the database connection
$dsCrossDatabse = Config::getConfigs("database")["OPAL_DB"];

// Connect to the database
$connection = Config::getDatabaseConnection("QUESTIONNAIRE");

// Check datbaase connection
if (!$connection)
{
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

// Get the patient preferred language
$wsLanguage = $rowPI['Language'];
// $wsLanguage = 'EN'; // TEST ONLY

// Step 2) Retrieve Patient Serial Number from QuestionnaireDB from Patient ID ($wsPatientID)
/*$wsSQLPSN = "select PatientSerNum
            from Patient
            where PatientID = $wsPatientID";
$qSQLPSN = $connection->query($wsSQLPSN);
$rowPSN = $qSQLPSN->fetchAll()[0];

// Step 3) Retrieve the Last Questionnaire Responses
$wsSQLQR = "select max(PatientQuestionnaireSerNum) PatientQuestionnaireSerNum, QuestionnaireSerNum,
                DATE_FORMAT(max(DateTimeAnswered), '%Y-%m-%d') as LastDateTimeAnswered, count(*) as Total
            from PatientQuestionnaire
            where PatientSerNum = " . $rowPSN['PatientSerNum'] .
            " and QuestionnaireSerNum = $wsReportID
            group by QuestionnaireSerNum
            order by max(PatientQuestionnaireSerNum) desc
            ";
$qSQLQR = $connection->query($wsSQLQR);
$rowQR = $qSQLQR->fetchAll()[0];*/

$qSQLQR = $connection->query("CALL getLastAnsweredQuestionnaire($rowPI[PatientSerNum],$wsReportID)");
$rowQR = $qSQLQR->fetchAll()[0];
$qSQLQR->closeCursor();

/*$wsSQLSeries = "select Q.QuestionQuestion, Q.QuestionQuestion_FR
                                from Question Q, QuestionnaireQuestion QQ
                                where QQ.QuestionnaireSerNum = $wsReportID
                                    and Q.QuestionSerNum = QQ.QuestionSerNum
                                order by QQ.OrderNum
                                ;";
$qSQLSeries = $connection->query($wsSQLSeries);*/
#echo $wsReportID;
$qSQLSeries = $connection->query("CALL queryQuestions('$wsReportID')");

$wsSeriesID = []; //unique id to select questions when the question texts are identical
$wsSeries = [];
$wsSeriesFR = [];
$wsRowCounter = 0;

foreach($qSQLSeries->fetchAll() as $rowSQLSeries)
{
    $wsSeriesID[$wsRowCounter] = $rowSQLSeries['QuestionnaireQuestionSerNum'];
    $wsSeries[$wsRowCounter] = $rowSQLSeries['QuestionText_EN'];
    $wsSeriesFR[$wsRowCounter] = $rowSQLSeries['QuestionText_FR'];
    $wsRowCounter = $wsRowCounter + 1;
}

if ($wsLanguage == 'EN')
{
    $wsResponse = $wsResponseEN;
    $wsReportTitle = $wsReportTitleEN;
    $wsName = $wsNameEN;
    $wsAge = $wsAgeEN;
    $wsSex = $wsSexEN;
    $wsLastValue = $LastValueEN;

    $wsMonth = $wsMonthEN;
    $wsShortMonth = $wsShortMonthEN;
    $wsWeekDays = $wsWeekDaysEN;
}
else
{
    $wsResponse = $wsResponseFR;
    $wsReportTitle = $wsReportTitleFR;
    $wsName = $wsNameFR;
    $wsAge = $wsAgeFR;
    $wsSex = $wsSexFR;
    $wsLastValue = $LastValueFR;

    $wsMonth = $wsMonthFR;
    $wsShortMonth = $wsShortMonthFR;
    $wsWeekDays = $wsWeekDaysFR;
};

//the return string, will be in JSON format
$jstring = [
    "langSetting" => [
        "months" => [$wsMonth],
        "weekdays" => [$wsWeekDays],
        "shortMonths" => [$wsShortMonth]
    ],
    "lastDateAnswered" => $rowQR["LastDateTimeAnswered"],
    "qData" => []
];

$wsSeries_Title = '';
$wsChartCounter = 0;

//for each question in the questionnaire, generate a highcharts object and add it to the JSON return string
for ($x = 0; $x < count($wsSeries); $x++)
{

    $wsChartCounter = $wsChartCounter + 1;

    if ($wsLanguage == 'EN')
    {
        $wsSeries_Title = filter_var($wsSeries[$x], FILTER_SANITIZE_STRING);
    }
    else
    {
        $wsSeries_Title = filter_var($wsSeriesFR[$x], FILTER_SANITIZE_STRING);
    };


    $questionAnswers = GetQuestionnaireData($wsPatientID,$wsSeries[$x],$wsReportID,$wsSeriesID[$x]);
    $lastValue = end($questionAnswers);
    $lastValue = $lastValue[1];

    $jstring["qData"][] = [
        "credits" => [
            "enabled" =>  "false"
        ],
        "exporting" =>  [
            "enabled" =>  "false",
            "filename" =>  "Chart$wsChartCounter"
        ],
        "chart" =>  [
            "type" =>  "line",
            "zoomType" =>  "x",
            "borderWidth" =>  "0"
        ],
        "title" =>  [
            "text" =>  "$wsSeries_Title"
        ],
        "tooltip" =>  [
            "formatter" =>  "null"
        ],
        "xAxis" =>  [
            "type" =>  "datetime",
            "minTickInterval" => "". 28*24*3600*1000,
            "startOnTick" =>  "true",
            "endOnTick" =>  "true",
            "labels" =>  [
                "style" =>  ["fontSize" =>  "14px"],
                "format" =>  "null"
            ]
        ],
        "yAxis" =>  [
            "min" =>  "0",
            "max" =>  "10",
            "startOnTick" =>  "false",
            "endOnTick" =>  "false",
            "title" =>  ["text" =>  "$wsResponse", "style" =>  ["fontSize" =>  "15px"]  ],
            "labels" =>  ["style" =>  ["fontSize" =>  "15px"] ],
                "plotLines" =>  [[
                    "color" =>  "rgba(0,0,0,0)",
                    "dashStyle" =>  "solid",
                    "value" =>  "3",
                    "width" =>  "2",
                    "label" =>  ["text" =>  "$wsLastValue: $lastValue", "align" =>  "right", "style" =>  ["fontSize" =>  "15px"]]
                        ]]
        ],
        "plotOptions" =>  [
            "line" =>  [
                "marker" =>  [
                    "enabled" =>  "true"
                ]
            ]
        ],
        "series" =>  [[
            "name" =>  "$wsSeries_Title",
            "showInLegend" =>  "false",
            "data" =>  $questionAnswers,
            "tooltip" =>  [
                "valueDecimals" =>  "0"
            ]
        ]]

    ];

}

$jstring = utf8_encode_recursive($jstring);
echo json_encode($jstring,JSON_NUMERIC_CHECK);

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
