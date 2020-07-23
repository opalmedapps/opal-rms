<?php
//====================================================================================
// updateCheckinFile.php - php code to query the MySQL databases and extract the list of patients
// who are currently checked in for open appointments today in Medivisit (MySQL)
//====================================================================================
require("loadConfigs.php");

// Create MySQL DB connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

// Create Opal DB connection
//perform additional check to see if opal db exists -> Opal and ORMS are independent so we can't have queries failing if the opal db is moved/modified
//for now assume that only RVH patients have a questionniare
$opalOnline = 1;
try {$dbOpal = new PDO(OPAL_CONNECT,OPAL_USERNAME,OPAL_PASSWORD,$OPAL_OPTIONS);}
catch (PDOException $e) {$opalOnline = 0;}

if($opalOnline)
{
	$sqlOpal = "
		SELECT
			Patient.PatientId,
			Patient.PatientId2,
			Questionnaire.CompletionDate AS QuestionnaireCompletionDate,
			CASE
                		WHEN Questionnaire.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW() THEN 1
               			ELSE 0
            		END AS CompletedWithinLastWeek
		FROM
			Patient
			INNER JOIN Questionnaire ON Questionnaire.PatientSerNum = Patient.PatientSerNum
				AND Questionnaire.CompletedFlag = 1
				AND Questionnaire.CompletionDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND NOW()
		WHERE
			Patient.PatientId = :patId
		ORDER BY Questionnaire.CompletionDate DESC
		LIMIT 1";

	$queryOpal = $dbOpal->prepare($sqlOpal);
}

$json = [];

#-------------------------------------------------------------------------------------
# Now, get the Medivisit checkins from MySQL
#-------------------------------------------------------------------------------------
$sqlWRM = "
	SELECT
		MediVisitAppointmentList.AppointmentSerNum AS ScheduledActivitySer,
        MediVisitAppointmentList.AppointId AS AppointmentId,
        ClinicResources.Speciality,
		PatientLocation.ArrivalDateTime,
		LTRIM(RTRIM(MediVisitAppointmentList.AppointmentCode)) AS AppointmentName,
		Patient.LastName,
		Patient.FirstName,
		Patient.PatientId AS PatientIdRVH,
		Patient.PatientId_MGH AS PatientIdMGH,
		Patient.OpalPatient,
		Patient.SMSAlertNum,
		MediVisitAppointmentList.Status,
		LTRIM(RTRIM(MediVisitAppointmentList.ResourceDescription)) AS ResourceName,
		MediVisitAppointmentList.ScheduledDateTime AS ScheduledStartTime,
		hour(MediVisitAppointmentList.ScheduledDateTime) AS ScheduledStartTime_hh,
		minute(MediVisitAppointmentList.ScheduledDateTime) AS ScheduledStartTime_mm,
		TIMESTAMPDIFF(MINUTE,NOW(), MediVisitAppointmentList.ScheduledDateTime) AS TimeRemaining,
		TIMESTAMPDIFF(MINUTE,PatientLocation.ArrivalDateTime,NOW()) AS WaitTime,
		hour(PatientLocation.ArrivalDateTime) AS ArrivalDateTime_hh,
		minute(PatientLocation.ArrivalDateTime) AS ArrivalDateTime_mm,
		PatientLocation.CheckinVenueName AS VenueId,
        Patient.PatientSerNum AS PatientSer,
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
";

/* Process results */
$queryWRM = $dbWRM->query($sqlWRM);

while($row = $queryWRM->fetch(PDO::FETCH_ASSOC))
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

    	if($row["Status"] === "Completed") $row["RowType"] = "Completed";
    	elseif($row["ArrivalDateTime"] === NULL) $row["RowType"] = "NotCheckedIn";
    	else $row["RowType"] = "CheckedIn";

	//cross query OpalDB for questionnaire information
	if($opalOnline)
	{
		$queryOpal->execute([":patId" => $row["PatientIdRVH"]]);
		$resultOpal = $queryOpal->fetchAll()[0] ?? [];

		$lastCompleted = $resultOpal["CompletionDate"] ?? NULL;
		$lastCompleted = $resultOpal["QuestionnaireCompletionDate"] ?? NULL;
		$completedWithinWeek = $resultOpal["CompletedWithinLastWeek"] ?? NULL;

		$row["QStatus"] = ($completedWithinWeek === "1") ? "green-circle" : NULL;

		if(
		    ($lastCompleted !== NULL && $row["LastQuestionnaireReview"] === NULL)
		    ||
		    (
			($lastCompleted !== NULL && $row["LastQuestionnaireReview"] !== NULL)
			&& (new DateTime($lastCompleted))->getTimestamp() > (new DateTime($row["LastQuestionnaireReview"]))->getTimestamp()
		    )
		) $row["QStatus"] = "red-circle";
	}

	//certain fields must be marked as strings (or json encode will convert them to int)
	$row['PatientIdRVH'] = '\''. $row['PatientIdRVH'] .'\'';
	$row['PatientIdMGH'] = '\''. $row['PatientIdMGH'] .'\'';

	$json[$row["Speciality"]][] = $row;
}

//======================================================================================
// Open the checkinlist.txt file for writing and output the json data to the checkinlist file
//======================================================================================
foreach($json as $speciality => $data)
{
    //encode the data to JSON
    $data = utf8_encode_recursive($data);
    $data = json_encode($data,JSON_NUMERIC_CHECK);

    $checkinlist = fopen(CHECKIN_FILE_PATH ."_$speciality", "w") or die("Unable to open checkinlist file!");
    fwrite($checkinlist,$data);
    fclose($checkinlist);
}

#scan for the list of check in files. If any of them were not updated today, empty them
$path = dirname(CHECKIN_FILE_PATH);
$files = scandir($path);

$files = array_diff($files,[".",".."]);

foreach($files as $file)
{
    $modDate = (new DateTime())->setTimestamp(filemtime("$path/$file"))->format("Y-m-d");
    $today = (new DateTime())->format("Y-m-d");

    if($modDate === $today) continue;

    $handle = fopen("$path/$file","w");
    fwrite($handle,"[]");
    fclose($handle);
}

?>
