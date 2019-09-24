<?php
//script to get all height and weight records a patient has in the WRM database

require("configFileLive.php");

//get webpage parameters
$patientIdRVH = $_GET['patientIdRVH'];
$patientIdMGH = $_GET['patientIdMGH'];

$json = array(); //output array

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//==================================
//run query
//==================================
$sql = "
	SELECT 
		PatientMeasurement.Date,
		PatientMeasurement.Height,
		PatientMeasurement.Weight
	FROM
	(
		SELECT 
			PatientMeasurement.Date,
			PatientMeasurement.PatientSer,
			MAX(PatientMeasurement.LastUpdated) AS LU,
			MAX(PatientMeasurement.PatientMeasurementSer) AS PatientMeasurementSer
		FROM
			PatientMeasurement
			INNER JOIN Patient ON Patient.PatientSerNum = PatientMeasurement.PatientSer
				AND Patient.PatientId = '$patientIdRVH'
				AND Patient.PatientId_MGH = '$patientIdMGH'
		GROUP BY
			PatientMeasurement.Date
	) AS PM
	INNER JOIN PatientMeasurement ON PatientMeasurement.PatientSer = PM.PatientSer
		AND PatientMeasurement.PatientMeasurementSer = PM.PatientMeasurementSer
	ORDER BY PatientMeasurement.Date";

//process results
$query = $dbWRM->query($sql);

while($row = $query->fetch(PDO::FETCH_ASSOC))
{
	$json[] = $row;
}

//encode and return the json object
echo json_encode($json,JSON_NUMERIC_CHECK);

$dbWRM = null;

?>

