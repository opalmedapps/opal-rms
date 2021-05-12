<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Patient;
use Orms\Http;
use Orms\Opal;

$patientId = $_GET["patientId"] ?? NULL;
$questionnaireId = $_GET["rptID"] ?? NULL;

if($patientId === NULL || $questionnaireId === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Missing fields!");
}

$patient = Patient::getPatientById((int) $patientId);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

$questions = Opal::getPatientAnswersForChartTypeQuestionnaire($patient,(int) $questionnaireId);
$questions = Encoding::utf8_encode_recursive($questions);

//get the date of the last questionnaire that the patient answered
/** @psalm-suppress ArgumentTypeCoercion */
$lastDateAnswered = max(array_column(array_column($questions,"data"),"0"))[0];
$lastDateAnswered = (new DateTime())->setTimestamp($lastDateAnswered)->format("Y-m-d");

//convert all unix timestamps to millseconds for highcharts
$questions = array_map(function($x) {
    $x["data"] = array_map(function($y) {
        $y[0] = $y[0] *1000;
        return $y;
    },$x["data"]);

    return $x;
},$questions);

//the return array, will be in JSON format
$jstring = [
    "lastDateAnswered" => $lastDateAnswered,
    "qData" => []
];

//for each question in the questionnaire, generate a highcharts object and add it to the JSON return array
foreach($questions as $q)
{
    $lastAnswer = $q["data"][count($q["data"])-1][1] ?? NULL;

    $jstring["qData"][] = [
        "credits" => ["enabled" => FALSE],
        "exporting" => ["enabled" => FALSE],
        "chart" => [
            "type" => "line",
            "zoomType" => "x",
            "borderWidth" => 0
        ],
        "title" => ["text" => $q["question"]],
        "tooltip" => ["formatter" => NULL],
        "xAxis" => [
            "type" => "datetime",
            "minTickInterval" => 28*24*3600*1000,
            "startOnTick" => TRUE,
            "endOnTick" => TRUE,
            "labels" => [
                "style"  => ["fontSize" => "14px"],
                "format" => NULL
            ]
        ],
        "yAxis" => [
            "min" => 0,
            "max" => 10,
            "startOnTick" => FALSE,
            "endOnTick" => FALSE,
            "title" => [
                "text"  => ($q["language"] === "EN") ? "Response" : "Réponse",
                "style" => ["fontSize" => "15px"]
            ],
            "labels" => ["style" => ["fontSize" => "15px"]],
            "plotLines" => [
                [
                    "color" => "rgba(0,0,0,0)",
                    "dashStyle" => "solid",
                    "value" => 3,
                    "width" => 2,
                    "label" => [
                        "text"  => ($q["language"] === "EN") ? "Final Value: $lastAnswer" : "Valeur Finale: $lastAnswer",
                        "align" => "right",
                        "style" => ["fontSize" => "15px"]
                    ]
                ]
            ]
        ],
        "plotOptions" => ["line" => ["marker" => ["enabled" => TRUE]]],
        "series" => [
            [
                "name"         => $q["question"],
                "showInLegend" => FALSE,
                "data"         => $q["data"],
                "tooltip"      => ["valueDecimals" => 0]
            ]
        ]
    ];
}

echo json_encode($jstring);

?>
