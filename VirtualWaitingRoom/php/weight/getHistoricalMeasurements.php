<?php
//script to get all height and weight records a patient has in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";
require("../loadConfigs.php");

use Orms\Config;

//get webpage parameters
$patientId = $_GET['patientId'];

//connect to db
$dbh = Config::getDatabaseConnection("ORMS");

//==================================
//run query
//==================================
$query = $dbh->prepare("
    SELECT
        PatientMeasurement.Date,
        PatientMeasurement.Height,
        PatientMeasurement.Weight,
        PatientMeasurement.BSA,
        PatientMeasurement.PatientId AS Mrn
    FROM
    (
        SELECT
            PatientMeasurement.Date,
            PatientMeasurement.PatientSer,
            MAX(PatientMeasurement.Time) AS Lastest,
            MAX(PatientMeasurement.PatientMeasurementSer) AS PatientMeasurementSer
        FROM
            PatientMeasurement
        WHERE
            PatientMeasurement.PatientSer = :pSer
        GROUP BY
            PatientMeasurement.Date
    ) AS PM
    INNER JOIN PatientMeasurement ON PatientMeasurement.PatientSer = PM.PatientSer
        AND PatientMeasurement.PatientMeasurementSer = PM.PatientMeasurementSer
    ORDER BY PatientMeasurement.Date
");
$query->execute([":pSer" => $patientId]);

//encode and return the json object
$json = utf8_encode_recursive($query->fetchAll());
echo json_encode($json,JSON_NUMERIC_CHECK);

?>
