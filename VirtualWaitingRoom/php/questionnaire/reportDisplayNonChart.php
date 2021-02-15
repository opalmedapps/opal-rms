<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Opal;

$mrn             = $_GET["mrn"] ?? NULL;
$questionnaireId = $_GET["rptID"] ?? NULL;

if($mrn === NULL || $questionnaireId === NULL) {
    http_response_code(400);
    exit("Missing fields!");
}

$questions = Opal::getPatientAnswersForNonChartTypeQuestionnaire($mrn,(int) $questionnaireId);

//the return array, will be in JSON format
$jstring = [];

$lastAnsweredQuestionnaireId = 0;

// begin looping the questionnaires
foreach($questions as $question)
{
    //change the format of the date depending on the language
    //also set the date to empty if the previous question answered was part of the same patient questionnaire instance
    $displayDate = "";
    if($lastAnsweredQuestionnaireId !== $question["PatientQuestionnaireSerNum"])
    {
        $lastAnsweredQuestionnaireId = $question["PatientQuestionnaireSerNum"];
        $displayDate = (new DateTime($question["DateTimeAnswered"]));

        $lastUsedDate = $displayDate->format("Y-m-d");

        if($question["Language"] === "EN") {
            $displayDate = $displayDate->format("l jS F Y");
        }
        else
        {
            $weekDay = $displayDate->format("l");
            $month = $displayDate->format("F");

            $displayDate = $displayDate->format("l d F Y");

            $displayDate = preg_replace(
                ["/$weekDay/","/$month/"],
                [convertEnglishWeekDayToFrench($weekDay),convertEnglishMonthToFrench($month)],
                $displayDate
            );
        }
    }

    //sanitize question text
    $questionText = ($question["Language"] === "EN") ? $question["QuestionQuestion"] : $question["QuestionQuestion_FR"];
    $questionText = str_replace("<br />","\\n",$questionText);
    $questionText = addslashes($questionText);

    //display the scale if the question has a scale
    $scale = "";
    if($question["QuestionTypeSerNum"] === "2")
    {
        //in theory, there should only be two rows
        //first row is the minimum value, second is the max
        $min = $question["choices"][0];
        $max = $question["choices"][1];

        $scale = $min["ChoiceSerNum"] ." ". trim($min["ChoiceDescription"]) .str_repeat(" ",4) .str_repeat(" - ",15) .str_repeat(" ",4);
        $scale .= $max["ChoiceSerNum"] ." ". $max["ChoiceDescription"];
    };

    //separate the answers by a comma if there's more than one
    $answers = implode(", ",$question["answers"]);

    $jstring[] = [
        "DisplayDate"  => $displayDate,
        "Description"  => Encoding::utf8_encode_recursive($questionText),
        "Choice"       => Encoding::utf8_encode_recursive($scale),
        "Answer"       => Encoding::utf8_encode_recursive($answers)
    ];

};

echo json_encode($jstring);

function convertEnglishWeekDayToFrench(string $weekDay): string
{
    $weekDays = [
        "Sunday"    => "Dimanche",
        "Monday"    => "Lundi",
        "Tuesday"   => "Mardi",
        "Wednesday" => "Mercredi",
        "Thursday"  => "Jeudi",
        "Friday"    => "Vendredi",
        "Saturday"  => "Samedi"
    ];

    return $weekDays[$weekDay];
}

function convertEnglishMonthToFrench(string $month): string
{
    $months = [
        "January"      => "janvier",
        "February"     => "février",
        "March"        => "mars",
        "April"        => "avril",
        "May"          => "mai",
        "June"         => "juin",
        "July"         => "juillet",
        "August"       => "août",
        "September"    => "septembre",
        "October"      => "octobre",
        "November"     => "novembre",
        "December"     => "décembre",
    ];

    return $months[$month];
}

?>
