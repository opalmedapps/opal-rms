<?php declare(strict_types=1);

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

// Get Patient ID
$wsPatientID = $_GET['mrn'] ?? NULL;

// Exit if Patient ID is empty
if ($wsPatientID === NULL) exit("No mrn!");

// Get Report ID
$wsReportID = $_GET['rptID'] ?? NULL;

// Exit if Report ID is empty
if ($wsReportID === NULL) exit("No questionnaire id!");

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
    SELECT
        PatientSerNum
        ,PatientID
        ,TRIM(CONCAT(TRIM(FirstName),' ',TRIM(LastName))) AS Name
        ,LEFT(sex,1) as Sex
        ,DateOfBirth
        ,Age
        ,Language
    FROM
        Patient
    WHERE
        PatientID = $wsPatientID"
);
$qSQLPI->execute();
$rowPI = $qSQLPI->fetchAll()[0];

// Get the patient preferred language
$wsLanguage = $rowPI['Language'];

$qSQLQR = $connection->prepare("CALL getLastAnsweredQuestionnaire($rowPI[PatientSerNum],$wsReportID)");
$qSQLQR->execute();
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
$qSQLSeries = $connection->prepare("CALL queryQuestions('$wsReportID')");
$qSQLSeries->execute();

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

if($wsLanguage === "EN")
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

/**
 *
 * @return mixed[]
 * @throws Exception
 * @throws PDOException
 */
function GetQuestionnaireData(string $wsPatientID,string $wsrptID,string $wsQuestionnaireSerNum,string $qstID): array
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
    $dsCrossDatabase = Config::getApplicationSettings()->opalDb?->databaseName;

    // Connect to the database
    $connection = Database::getQuestionnaireConnection();

    // Prepare the output
    $output = [];

    if($connection !== NULL)
    {
        $query = $connection->prepare("CALL getQuestionNameAndAnswerByID('$wsPatientID',$wsQuestionnaireSerNum,'$wsrptID','$dsCrossDatabase','$qstID')");
        $query->execute();

        foreach($query->fetchAll() as $row)
        {
            // merge the output
            $output[] = [$row['DateTimeAnswered'] .'000', $row['Answer']];
        }
    }

    // return the output
    return $output;
}

?>
