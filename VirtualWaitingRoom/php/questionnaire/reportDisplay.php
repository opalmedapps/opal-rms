<?php

declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Hospital\OIE\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$patientId       = $_GET["patientId"] ?? null;
$questionnaireId = $_GET["questionnaireId"] ?? null;

if($patientId === null || $questionnaireId === null) {
    Http::generateResponseJsonAndExit(400, error: "Missing fields!");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

$questions = Fetch::getPatientAnswersForChartTypeQuestionnaire($patient, (int) $questionnaireId);

//get the date of the last questionnaire that the patient answered
if($questions[0]["answers"] === []) {
    $lastDateAnswered = null;
}
else {
    $lastDateAnswered = $questions[0]["answers"][count($questions[0]["answers"])-1]["dateTimeAnswered"];
    $lastDateAnswered = (new DateTime())->setTimestamp($lastDateAnswered)->format("Y-m-d");
}

//convert all unix timestamps to millseconds for highcharts
$questions = array_map(function($x) {
    $x["answers"] = array_map(function($y) {
        $y["dateTimeAnswered"] = $y["dateTimeAnswered"] *1000;
        return $y;
    }, $x["answers"]);

    return $x;
}, $questions);

//the return array, will be in JSON format
$jstring = [
    "lastDateAnswered" => $lastDateAnswered,
    "qData" => []
];

//for each question in the questionnaire, generate a highcharts object and add it to the JSON return array
foreach($questions as $q)
{
    $lastAnswer = $questions[0]["answers"][count($questions[0]["answers"])-1]["answer"];

    $jstring["qData"][] = [
        "credits" => ["enabled" => false],
        "exporting" => ["enabled" => false],
        "chart" => [
            "type" => "line",
            "zoomType" => "x",
            "borderWidth" => 0
        ],
        "title" => ["text" => $q["questionTitle"]],
        "tooltip" => ["formatter" => null],
        "xAxis" => [
            "type" => "datetime",
            "minTickInterval" => 28*24*3600*1000,
            "startOnTick" => true,
            "endOnTick" => true,
            "labels" => [
                "style"  => ["fontSize" => "14px"],
                "format" => null
            ]
        ],
        "yAxis" => [
            "min" => 0,
            "max" => 10,
            "startOnTick" => false,
            "endOnTick" => false,
            "title" => [
                "text"  => "Response",
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
                        "text"  => "Final Value: $lastAnswer",
                        "align" => "right",
                        "style" => ["fontSize" => "15px"]
                    ]
                ]
            ]
        ],
        "plotOptions" => ["line" => ["marker" => ["enabled" => true]]],
        "series" => [
            [
                "name"         => $q["questionTitle"],
                "showInLegend" => false,
                "data"         => array_map(fn($x) => [$x["dateTimeAnswered"],$x["answer"]], $q["answers"]), //convert to non-assoc array for highcharts
                "tooltip"      => ["valueDecimals" => 0]
            ]
        ]
    ];
}

echo json_encode($jstring);
