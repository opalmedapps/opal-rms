<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\External\LegacyOpalAdmin\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId       = $params["patientId"] ?? null;
$questionnaireId = $params["questionnaireId"] ?? null;
$visualization   = $params["visualization"] ?? null;

if($patientId === null || $questionnaireId === null || $visualization === null) {
    Http::generateResponseJsonAndExit(400, error: "Missing fields!");
}

$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

//the return array
$data = [
    "lastDateAnswered" => null,
    "questions" => []
];

if((int) $visualization === 1)
{
    $questions = Fetch::getPatientAnswersForChartTypeQuestionnaire($patient, (int) $questionnaireId);

    //get the date of the last questionnaire that the patient answered
    if($questions[0]["answers"] === []) {
        $lastDateAnswered = null;
    }
    else {
        $lastDateAnswered = $questions[0]["answers"][count($questions[0]["answers"])-1]["dateTimeAnswered"];
        $lastDateAnswered = (new DateTime())->setTimestamp($lastDateAnswered)->format("Y-m-d");
    }

    $data["lastDateAnswered"] = $lastDateAnswered;

    //convert all unix timestamps to millseconds for highcharts
    $questions = array_map(function($x) {
        $x["answers"] = array_map(function($y) {
            $y["dateTimeAnswered"] = $y["dateTimeAnswered"] *1000;
            return $y;
        }, $x["answers"]);

        return $x;
    }, $questions);

    //for each question in the questionnaire, generate a highcharts object and add it to the JSON return array
    $data["questions"] = array_map(fn($x) => [
        "credits" => ["enabled" => false],
        "exporting" => ["enabled" => false],
        "chart" => [
            "type" => "line",
            "zoomType" => "x",
            "borderWidth" => 0
        ],
        "title" => ["text" => $x["questionTitle"]],
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
                        "text"  => "Final Value: ". $x["answers"][count($questions[0]["answers"])-1]["answer"],
                        "align" => "right",
                        "style" => ["fontSize" => "15px"]
                    ]
                ]
            ]
        ],
        "plotOptions" => ["line" => ["marker" => ["enabled" => true]]],
        "series" => [
            [
                "name"         => $x["questionTitle"],
                "showInLegend" => false,
                "data"         => array_map(fn($y) => [$y["dateTimeAnswered"],$y["answer"]], $x["answers"]), //convert to non-assoc array for highcharts
                "tooltip"      => ["valueDecimals" => 0]
            ]
        ]
    ],$questions);
}
else
{
    $questions = Fetch::getPatientAnswersForNonChartTypeQuestionnaire($patient, (int) $questionnaireId);

    $data["lastDateAnswered"] = $questions[0]["dateTimeAnswered"] ?? null;

    $lastAnsweredQuestionnaireId = 0;

    // begin looping the questionnaires
    foreach($questions as $question)
    {
        //set the date to empty if the previous question answered was part of the same patient questionnaire instance
        $displayDate = "";
        if($lastAnsweredQuestionnaireId !== $question["questionnaireAnswerId"])
        {
            $lastAnsweredQuestionnaireId = $question["questionnaireAnswerId"];
            $displayDate = (new DateTime($question["dateTimeAnswered"]))->format("l jS F Y");
        }

        //sanitize question text
        $questionText = $question["questionTitle"];
        $questionText = str_replace("<br />", "\\n", $questionText);
        $questionText = addslashes($questionText);

        //display the scale if the question has a scale
        $scale = "";
        if($question["hasScale"] === true)
        {
            //in theory, there should only be two rows
            //first row is the minimum value, second is the max
            $min = $question["options"][0];
            $max = $question["options"][1];

            $scale = $min["value"] ." ". trim($min["description"]) .str_repeat(" ", 4) .str_repeat(" - ", 15) .str_repeat(" ", 4);
            $scale .= $max["value"] ." ". $max["description"];
        };

        //separate the answers by a comma if there's more than one
        $answers = implode(", ", $question["answers"]);

        $data["questions"][] = [
            "DisplayDate"  => $displayDate,
            "Description"  => $questionText,
            "Choice"       => $scale,
            "Answer"       => $answers
        ];
    };
}

Http::generateResponseJsonAndExit(200, data: $data);
