<?php
//====================================================================================
// php code to query the database and extract the list of patients
// who are currently checked in for open appointments today
//====================================================================================

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Config;
use Orms\DataAccess\Database;
use Orms\Hospital\Opal;

// Create MySQL DB connection
$dbh = Database::getOrmsConnection();

$json = [];

$queryWRM = $dbh->prepare("
    SELECT
        MV.AppointmentSerNum AS AppointmentId,
        MV.AppointId AS SourceId,
        SG.SpecialityGroupId,
        PL.ArrivalDateTime,
        COALESCE(AC.DisplayName,AC.AppointmentCode) AS AppointmentName,
        P.LastName,
        P.FirstName,
        P.PatientSerNum AS PatientId,
        PH.MedicalRecordNumber AS Mrn,
        H.HospitalCode AS Site,
        P.OpalPatient,
        P.SMSAlertNum,
        CASE WHEN P.LanguagePreference IS NOT NULL THEN P.LanguagePreference ELSE 'French' END AS LanguagePreference,
        MV.Status,
        LTRIM(RTRIM(CR.ResourceName)) AS ResourceName,
        MV.ScheduledDateTime AS ScheduledStartTime,
        HOUR(MV.ScheduledDateTime) AS ScheduledStartTime_hh,
        MINUTE(MV.ScheduledDateTime) AS ScheduledStartTime_mm,
        TIMESTAMPDIFF(MINUTE,NOW(), MV.ScheduledDateTime) AS TimeRemaining,
        TIMESTAMPDIFF(MINUTE,PL.ArrivalDateTime,NOW()) AS WaitTime,
        HOUR(PL.ArrivalDateTime) AS ArrivalDateTime_hh,
        MINUTE(PL.ArrivalDateTime) AS ArrivalDateTime_mm,
        PL.CheckinVenueName AS VenueId,
        MV.AppointSys AS CheckinSystem,
        DATE_FORMAT(P.DateOfBirth,'%b %d') AS Birthday,
        PM.Date AS WeightDate,
        PM.Weight,
        PM.Height,
        PM.BSA,
        (SELECT DATE_FORMAT(MAX(ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE PatientSer = P.PatientSerNum) AS LastQuestionnaireReview
    FROM
        MediVisitAppointmentList MV
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
        INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            AND PH.HospitalId = SG.HospitalId
            AND PH.Active = 1
        INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
        LEFT JOIN PatientLocation PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
        LEFT JOIN PatientMeasurement PM ON PM.PatientMeasurementSer =
            (
                SELECT
                    PMM.PatientMeasurementSer
                FROM
                    PatientMeasurement PMM
                WHERE
                    PMM.PatientSer = P.PatientSerNum
                    AND PMM.Date BETWEEN DATE_SUB(CURDATE(), INTERVAL 21 DAY) AND NOW()
                ORDER BY
                    PMM.Date DESC,
                    PMM.Time DESC
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
            $questionnaire = Opal::getLastCompletedPatientQuestionnaire($row["Mrn"],$row["Site"]);
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
        "OpalPatient",
        "PatientId",
        "AppointmentId",
        "SpecialityGroupId"] as $x) {
        $row[$x] = (int) $row[$x];
    }

    foreach([
        "BSA",
        "Height",
        "Weight"] as $x) {
        $row[$x] = (float) $row[$x];
    }

    $json[$row["SpecialityGroupId"]][] = $row;
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
