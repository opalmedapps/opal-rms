<?php
//====================================================================================
// updateCheckinFile.php - php code to query the database and extract the list of patients
// who are currently checked in for open appointments today
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Config;
use Orms\Database;
use Orms\Opal;

// Create MySQL DB connection
$dbh = Database::getOrmsConnection();

$json = [];

$queryWRM = $dbh->prepare("
    SELECT
        MV.AppointmentSerNum AS AppointmentId,
        MV.AppointId AS SourceId,
        ClinicResources.Speciality,
        PL.ArrivalDateTime,
        LTRIM(RTRIM(MV.AppointmentCode)) AS AppointmentName,
        P.LastName,
        P.FirstName,
        P.PatientSerNum AS PatientId,
        P.PatientId AS Mrn,
        P.OpalPatient,
        P.SMSAlertNum,
        CASE WHEN P.LanguagePreference IS NOT NULL THEN P.LanguagePreference ELSE 'French' END AS LanguagePreference,
        MV.Status,
        LTRIM(RTRIM(MV.ResourceDescription)) AS ResourceName,
        MV.ScheduledDateTime AS ScheduledStartTime,
        HOUR(MV.ScheduledDateTime) AS ScheduledStartTime_hh,
        MINUTE(MV.ScheduledDateTime) AS ScheduledStartTime_mm,
        TIMESTAMPDIFF(MINUTE,NOW(), MV.ScheduledDateTime) AS TimeRemaining,
        TIMESTAMPDIFF(MINUTE,PL.ArrivalDateTime,NOW()) AS WaitTime,
        HOUR(PL.ArrivalDateTime) AS ArrivalDateTime_hh,
        MINUTE(PL.ArrivalDateTime) AS ArrivalDateTime_mm,
        PL.CheckinVenueName AS VenueId,
        MV.AppointSys AS CheckinSystem,
        SUBSTRING(P.SSN,1,3) AS SSN,
        SUBSTRING(P.SSN,9,2) AS DAYOFBIRTH,
        SUBSTRING(P.SSN,7,2) AS MONTHOFBIRTH,
        PatientMeasurement.Date AS WeightDate,
        PatientMeasurement.Weight,
        PatientMeasurement.Height,
        PatientMeasurement.BSA,
        (SELECT DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE TEMP_PatientQuestionnaireReview.PatientSer = P.PatientSerNum) AS LastQuestionnaireReview
    FROM
        MediVisitAppointmentList MV
        INNER JOIN ClinicResources ON ClinicResources.ResourceName = MV.ResourceDescription
        INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
        LEFT JOIN PatientLocation PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
        LEFT JOIN PatientMeasurement ON PatientMeasurement.PatientMeasurementSer =
            (
                SELECT
                    PM.PatientMeasurementSer
                FROM
                    PatientMeasurement PM
                WHERE
                    PM.PatientSer = P.PatientSerNum
                    AND PM.Date BETWEEN DATE_SUB(CURDATE(), INTERVAL 21 DAY) AND NOW()
                ORDER BY
                    PM.Date DESC,
                    PM.Time DESC
                LIMIT 1
            )
    WHERE
        MV.ScheduledDate = CURDATE()
        AND MV.Status IN ('Open','Completed','In Progress')
    ORDER BY
        P.LastName,
        MV.ScheduledDateTime,
        MV.AppointmentSerNum
");
$queryWRM->execute();

foreach($queryWRM->fetchAll() as $row)
{
    //perform some processing
    if($row["SMSAlertNum"]) $row["SMSAlertNum"] = substr($row["SMSAlertNum"],0,3) ."-". substr($row["SMSAlertNum"],3,3) ."-". substr($row["SMSAlertNum"],6,4);

    //if the weight was entered today, indicate it
    if(time() - (60*60*24) < strtotime($row['WeightDate']))
    {
        $row['WeightDate'] = 'Today';
    }
    else
    {
        $row['WeightDate'] = 'Old';
    }

    $row["ArrivalDateTime"] = $row["ArrivalDateTime"] ?? ""; //just to get psalm to stop complaining

    if($row["Status"] === "Completed")          $row["RowType"] = "Completed";
    elseif($row["ArrivalDateTime"] === "")      $row["RowType"] = "NotCheckedIn";
    else                                        $row["RowType"] = "CheckedIn";

    //cross query OpalDB for questionnaire information
    if($row["OpalPatient"] === "1")
    {
        try {
            $questionnaire = Opal::getLastCompletedPatientQuestionnaire($row["Mrn"]);
        }
        catch(Exception) {
            $questionnaire = [];
        }

        if($questionnaire !== [])
        {
            $lastCompleted = $questionnaire["QuestionnaireCompletionDate"] ?? NULL;
            $completedWithinWeek = $questionnaire["CompletedWithinLastWeek"] ?? NULL;

            $row["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : NULL;

            if(
                (
                    $lastCompleted !== NULL
                    && $row["LastQuestionnaireReview"] === NULL
                )
                ||
                (
                    ($lastCompleted !== NULL && $row["LastQuestionnaireReview"] !== NULL)
                    && (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($row["LastQuestionnaireReview"]))->getTimestamp()
                )
            ) $row["QStatus"] = "red-circle";
        }
    }

    //set certain fields to int
    foreach([
        "ArrivalDateTime_hh",
        "ArrivalDateTime_mm",
        "ScheduledStartTime_hh",
        "ScheduledStartTime_mm",
        "TimeRemaining",
        "WaitTime",
        "DAYOFBIRTH",
        "MONTHOFBIRTH",
        "OpalPatient",
        "PatientId",
        "AppointmentId"] as $x) {
        $row[$x] = (int) $row[$x];
    }

    foreach([
        "BSA",
        "Height",
        "Weight"] as $x) {
        $row[$x] = (float) $row[$x];
    }

    $json[$row["Speciality"]][] = $row;
}

//======================================================================================
// Open the checkinlist.txt file for writing and output the json data to the checkinlist file
//======================================================================================
$checkInFilePath = Config::getApplicationSettings()->environment->basePath ."/VirtualWaitingRoom/checkin";

foreach($json as $speciality => $data)
{
    //encode the data to JSON
    $data = Encoding::utf8_encode_recursive($data);
    $data = json_encode($data) ?: "[]";

    $checkinlist = fopen("$checkInFilePath/$speciality.json", "w");
    if($checkinlist === FALSE) {
        die("Unable to open checkinlist file!");
    }

    fwrite($checkinlist,$data);
    fclose($checkinlist);
}

#scan for the list of check in files. If any of them were not updated today, empty them
$path = dirname($checkInFilePath);
$files = scandir($path) ?: [];

$files = array_filter($files,function($x) {
    return preg_match("/\.json/",$x) ? TRUE : FALSE ;
});

foreach($files as $file)
{
    $modDate = (new DateTime())->setTimestamp(filemtime("$path/$file") ?: 0)->format("Y-m-d");
    $today = (new DateTime())->format("Y-m-d");

    if($modDate === $today) continue;

    $handle = fopen("$path/$file","w");
    if($handle === FALSE) continue;

    fwrite($handle,"[]");
    fclose($handle);
}

?>
