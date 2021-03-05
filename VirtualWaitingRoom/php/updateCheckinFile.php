<?php
//====================================================================================
// updateCheckinFile.php - php code to query the MySQL databases and extract the list of patients
// who are currently checked in for open appointments today in Medivisit (MySQL)
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Database;
use Orms\Opal;

// Create MySQL DB connection
$dbh = Database::getOrmsConnection();

$json = [];

$queryWRM = $dbh->prepare("
    SELECT
        MediVisitAppointmentList.AppointmentSerNum AS ScheduledActivitySer,
        MediVisitAppointmentList.AppointId AS AppointmentId,
        ClinicResources.Speciality,
        PatientLocation.ArrivalDateTime,
        LTRIM(RTRIM(MediVisitAppointmentList.AppointmentCode)) AS AppointmentName,
        Patient.LastName,
        Patient.FirstName,
        Patient.PatientSerNum AS PatientId,
        Patient.PatientId AS Mrn,
        Patient.OpalPatient,
        Patient.SMSAlertNum,
        CASE WHEN Patient.LanguagePreference IS NOT NULL THEN Patient.LanguagePreference ELSE 'French' END AS LanguagePreference,
        MediVisitAppointmentList.Status,
        LTRIM(RTRIM(MediVisitAppointmentList.ResourceDescription)) AS ResourceName,
        MediVisitAppointmentList.ScheduledDateTime AS ScheduledStartTime,
        HOUR(MediVisitAppointmentList.ScheduledDateTime) AS ScheduledStartTime_hh,
        MINUTE(MediVisitAppointmentList.ScheduledDateTime) AS ScheduledStartTime_mm,
        TIMESTAMPDIFF(MINUTE,NOW(), MediVisitAppointmentList.ScheduledDateTime) AS TimeRemaining,
        TIMESTAMPDIFF(MINUTE,PatientLocation.ArrivalDateTime,NOW()) AS WaitTime,
        HOUR(PatientLocation.ArrivalDateTime) AS ArrivalDateTime_hh,
        MINUTE(PatientLocation.ArrivalDateTime) AS ArrivalDateTime_mm,
        PatientLocation.CheckinVenueName AS VenueId,
        MediVisitAppointmentList.AppointSys AS CheckinSystem,
        SUBSTRING(Patient.SSN,1,3) AS SSN,
        SUBSTRING(Patient.SSN,9,2) AS DAYOFBIRTH,
        SUBSTRING(Patient.SSN,7,2) AS MONTHOFBIRTH,
        PatientMeasurement.Date AS WeightDate,
        PatientMeasurement.Weight,
        PatientMeasurement.Height,
        PatientMeasurement.BSA,
        (SELECT DATE_FORMAT(MAX(TEMP_PatientQuestionnaireReview.ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE TEMP_PatientQuestionnaireReview.PatientSer = Patient.PatientSerNum) AS LastQuestionnaireReview
    FROM
        MediVisitAppointmentList
        INNER JOIN ClinicResources ON ClinicResources.ResourceName = MediVisitAppointmentList.ResourceDescription
        INNER JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
        LEFT JOIN PatientLocation ON PatientLocation.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
        LEFT JOIN PatientMeasurement ON PatientMeasurement.PatientMeasurementSer =
            (
                SELECT
                    PM.PatientMeasurementSer
                FROM
                    PatientMeasurement PM
                WHERE
                    PM.PatientSer = Patient.PatientSerNum
                    AND PM.Date BETWEEN DATE_SUB(CURDATE(), INTERVAL 21 DAY) AND NOW()
                ORDER BY
                    PM.Date DESC,
                    PM.Time DESC
                LIMIT 1
            )
    WHERE
        MediVisitAppointmentList.ScheduledDate = CURDATE()
        AND MediVisitAppointmentList.Status IN ('Open','Completed','In Progress')
    ORDER BY
        Patient.LastName,
        MediVisitAppointmentList.ScheduledDateTime,
        MediVisitAppointmentList.AppointmentSerNum
");
$queryWRM->execute();

foreach($queryWRM->fetchAll() as $row)
{
    //perform some processing
    $row['Identifier'] = $row['ScheduledActivitySer'] ."Medivisit";
    $row['AppointmentId'] = "MEDI". $row['AppointmentId'];

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
        "ScheduledActivitySer"] as $x) {
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
    $data = utf8_encode_recursive($data);
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
