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

$questions = Fetch::getPatientAnswersForNonChartTypeQuestionnaire($patient, (int) $questionnaireId);

//the return array, will be in JSON format
$jstring = [];

$lastAnsweredQuestionnaireId = 0;

// begin looping the questionnaires
foreach($questions as $question)
{
    //change the format of the date depending on the language
    //also set the date to empty if the previous question answered was part of the same patient questionnaire instance
    $displayDate = "";
    if($lastAnsweredQuestionnaireId !== $question["questionnaireAnswerId"])
    {
        $lastAnsweredQuestionnaireId = $question["questionnaireAnswerId"];
        $displayDate = (new DateTime($question["dateTimeAnswered"]));

        $lastUsedDate = $displayDate->format("Y-m-d");
        $displayDate = $displayDate->format("l jS F Y");
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

    $jstring[] = [
        "DisplayDate"  => $displayDate,
        "Description"  => $questionText,
        "Choice"       => $scale,
        "Answer"       => $answers
    ];

};

echo json_encode($jstring);
