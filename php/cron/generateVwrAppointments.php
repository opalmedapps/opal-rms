<?php

declare(strict_types=1);

// script that loads all of the current day's appointments and saves them to a json file for use in the virtual waiting room

//known bug: if a speciality has only one appoimtment for that day, and the patient checks in for it, and then the appointment date is changed to another date, the json file for that speciality won't be cleared until the next day

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\DataAccess\ReportAccess;
use Orms\DateTime;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

//get questionnaire data for opal patients
$lastCompletedQuestionnaires = json_decode(@file_get_contents(Config::getApplicationSettings()->environment->completedQuestionnairePath) ?: "{}",true) ?? [];
$lastCompletedQuestionnaires = array_filter($lastCompletedQuestionnaires);
$lastCompletedQuestionnaires = array_map(function($x) {
    $x["completionDate"] = new DateTime($x["completionDate"]);
    /** @psalm-suppress InvalidArrayOffset */
    $x["lastUpdated"]    = new DateTime($x["lastUpdated"]);

    return $x;
},$lastCompletedQuestionnaires);

//generate a list of today's appointments
$appointments = ReportAccess::getCurrentDaysAppointments();

$appointments = array_map(function($x) use ($lastCompletedQuestionnaires) {
    //sex is represented with the first letter only
    $x["Sex"] = mb_substr($x["Sex"], 0, 1);

    //format phone number
    if($x["PhoneNumber"] !== null) {
        $x["PhoneNumber"] = mb_substr($x["PhoneNumber"], 0, 3) ."-". mb_substr($x["PhoneNumber"], 3, 3) ."-". mb_substr($x["PhoneNumber"], 6, 4);
    }

    //if the weight was entered today, indicate it
    $x["WeightDate"] = ($x["WeightDate"] !== null && time() - (60*60*24) < strtotime($x["WeightDate"])) ? "Today" : "Old";

    //determine what the checked in status is
    $x["RowType"] = "CheckedIn";
    if($x["Status"] === "Completed") {
        $x["RowType"] = "Completed";
    }
    elseif($x["ArrivalDateTime"] === null)  {
        $x["RowType"] = "NotCheckedIn";
    }

    //compare questionnaire information
    $x["QStatus"] = null;
    if($x["OpalPatient"] === 1)
    {
        $questionnaire = $lastCompletedQuestionnaires[$x["PatientId"]] ?? null;
        if($questionnaire !== null)
        {
            $oneWeekAgo = (new DateTime())->modifyN("midnight")?->modifyN("-1 week") ?? throw new Exception("Invalid datetime");
            $completedWithinWeek = ($oneWeekAgo <= $questionnaire["completionDate"]);

            $lastQuestionnaireReview = ($x["LastQuestionnaireReview"] !== null) ? new DateTime($x["LastQuestionnaireReview"]) : null;

            if($lastQuestionnaireReview === null || $questionnaire["completionDate"] > $lastQuestionnaireReview) {
                $x["QStatus"] = "red-circle";
            }
            elseif($completedWithinWeek === true) {
                $x["QStatus"] = "green-circle";
            }
        }
    }

    return $x;
}, $appointments);

//group appointments by speciality group
$appointments = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($appointments,"SpecialityGroupId");

//scan for the list of appointment files. If any of them were not updated today, empty them
$path = Config::getApplicationSettings()->environment->basePath ."/tmp";

$today = (new DateTime())->format("Y-m-d");
$files = scandir($path) ?: [];

$files = array_filter($files, fn($x) => preg_match("/\.vwr\.json/", $x) ? true : false);

foreach($files as $file)
{
    $modDate = (new DateTime())->setTimestamp(filemtime("$path/$file") ?: 0)->format("Y-m-d");

    if($modDate === $today) continue;

    $handle = fopen("$path/$file", "w");
    if($handle === false) continue;

    fwrite($handle, "[]");
    fclose($handle);
}

//for each speciality, dump the daily appointments in a json file
foreach($appointments as $speciality => $data)
{
    //encode the data to JSON
    $data = Encoding::utf8_encode_recursive($data);
    $data = json_encode($data) ?: "[]";

    $checkinlist = fopen("$path/$speciality.vwr.json", "w");
    if($checkinlist === false) {
        die("Unable to open checkinlist file!");
    }

    fwrite($checkinlist, $data);
    fclose($checkinlist);
}
