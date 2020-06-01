<?php
declare(strict_types=1);
#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the ORMS database.
#---------------------------------------------------------------------------------------------------------------

require_once __DIR__."/../loadConfigs.php";

#get input parameters

$sDateInit = $_GET["sDate"] ?? NULL;
$eDateInit = $_GET["eDate"] ?? NULL;
$sTime = $_GET["sTime"] ?? NULL;
$eTime = $_GET["eTime"] ?? NULL;
$clinic = $_GET["clinic"] ?? NULL;
$statusConditions = [
    "completed" => isset($_GET["comp"]) ? "'Completed'" : NULL,
    "open" => isset($_GET["openn"]) ? "'Open'" : NULL,
    "cancelled" => isset($_GET["canc"]) ? "'Cancelled'" : NULL,
];

$checkForArrived = isset($_GET["arrived"]) ? TRUE : NULL;
$checkForNotArrived = isset($_GET["notArrived"]) ? TRUE : NULL;
$appType = $_GET["type"] ?? NULL;
$specificApp = $_GET["specificType"] ?? "NULL";
$appcType = $_GET["ctype"] ?? NULL;
$cspecificApp = $_GET["cspecificType"] ?? "NULL";
$appdType = $_GET["dtype"] ?? NULL;
$dspecificApp = $_GET["dspecificType"] ?? "NULL";
$checkRamqExpiration = isset($_GET["checkRamqExpiration"]) ? TRUE : NULL;

$sDate = "$sDateInit $sTime";
$eDate = "$eDateInit $eTime";

#query the database
$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);


$dbOpal = new PDO(OPAL_CONNECT,OPAL_USERNAME,OPAL_PASSWORD,$OPAL_OPTIONS);
$sqlOpal = "
    SELECT
        DT.Name_EN
    FROM
        (SELECT P.PatientId, D.DiagnosisCode
         from Patient P INNER JOIN Diagnosis D ON D.PatientSerNum = P.PatientSerNum
         WHERE P.PatientId = ?
         ORDER BY D.LastUpdated DESC) DP,
         DiagnosisCode DC,
         DiagnosisTranslation DT
    WHERE DC.DiagnosisCode = DP.DiagnosisCode
    AND DC.DiagnosisTranslationSerNum = DT.DiagnosisTranslationSerNum
    LIMIT 1";

$queryOpal = $dbOpal->prepare($sqlOpal);


$specialityFilter = "ClinicResources.Speciality = '$clinic'";

$activeStatusConditions = array_filter($statusConditions);
//if($specificApp === NULL) print("t");
//else print("x");
$statusFilter = " AND MV.Status IN (" . implode(",", $activeStatusConditions) . ")";
$appFilter = ($appType === "all") ? "" : " AND MV.ResourceDescription IN (" . implode(",", explode(",",$specificApp)) . ")";
$cappFilter = ($appcType === "all") ? "" : " AND MV.AppointmentCode IN (" . implode(",", explode(",",$cspecificApp)) . ")";
//$appFilter = ($appType === "all") ? "" : " AND MV.ResourceDescription IN :resDesc ";
//$cappFilter = ($appcType === "all") ? "" : " AND MV.AppointmentCode IN :appCode ";

/*print_r(explode(",",$dspecificApp));
if(in_array("Sarcoma", explode(",",$dspecificApp))) print "good";

else print "bad";*/


$sql = "
    SELECT
        MV.AppointmentSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        Patient.SSN,
        MV.ResourceDescription,
        MV.Resource,
        MV.AppointmentCode,
        MV.Status,
        MV.ScheduledDate,
        MV.ScheduledTime,
        MV.CreationDate,
        MV.ReferringPhysician,
        (select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as CurrentCheckInTime,
        (select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as PreviousCheckInTime,
        MV.MedivisitStatus
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = Patient.PatientSerNum
        AND MV.Status != 'Deleted'
        AND MV.ResourceDescription IN (
            SELECT DISTINCT
                ClinicResources.ResourceName
            FROM ClinicResources
            WHERE $specialityFilter
        )
        AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
        $statusFilter
        $appFilter
        $cappFilter

    ORDER BY
        MV.ScheduledDate,
        MV.ScheduledTime";




$query = $dbh->prepare($sql);

//if ($appFilter !== "") $query->bindValue(":resDesc", $specificApp);
//if ($cappFilter !== "") $query->bindValue(":appCode", $cspecificApp);
$query->bindValue(":sDate", $sDate);
$query->bindValue(":eDate", $eDate);

$query->execute();

$listOfAppointments = [];

while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    #filter apppointments on whether the patient checked in for it
    $checkInTime = $row["CurrentCheckInTime"] ?? $row["PreviousCheckInTime"] ?? NULL;

    if ($checkForArrived === TRUE && $checkForNotArrived === TRUE) $filterAppointment = FALSE;
    elseif ($checkForArrived === TRUE && $checkInTime === NULL) $filterAppointment = TRUE;
    elseif ($checkForNotArrived === TRUE && $checkInTime !== NULL) $filterAppointment = TRUE;
    else $filterAppointment = FALSE;

    if ($filterAppointment) continue;

    #if ramq expiration checking is enabled, check if the ramq is expired
/*
    $ramqExpired = FALSE;
    if ($checkRamqExpiration) {
        $ramqInfo = AdtWebservice::getRamqInformation($row["SSN"]);
        $row["ssnExp"] = $ramqInfo["Expiration"];
        if ($ramqInfo["Status"] === "Expired" || $ramqInfo["Status"] === "Error") $ramqExpired = TRUE;
    }
*/

    #perform some processing
    if (isset($row["ssnExp"]) && $row["ssnExp"] !== NULL) $row["ssnExp"] = (new DateTime($row["ssnExp"]))->format("ym");
    $row["ScheduledTime"] = substr($row["ScheduledTime"], 0, -3);

    $queryOpal->execute([$row['PatientId']]);
    $resultOpal = $queryOpal->fetch(PDO::FETCH_ASSOC);

    $row["Diagnosis"] = !empty($resultOpal["Name_EN"]) ? $resultOpal["Name_EN"] : "";

    if($appdType ==="all" || in_array( $row["Diagnosis"],explode(",",$dspecificApp)) ){
        $listOfAppointments[] = [
            "fname" => $row["FirstName"],
            "lname" => $row["LastName"],
            "pID" => $row["PatientId"],
            "ssn" => [
                "num" => $row["SSN"],
                "expDate" => $row["ssnExp"] ?? NULL,
                "expired" => $ramqExpired,
            ],
            "appName" => $row["ResourceDescription"],
            "appClinic" => $row["Resource"],
            "appType" => $row["AppointmentCode"],
            "appStatus" => $row["Status"],
            "appDay" => $row["ScheduledDate"],
            "appTime" => $row["ScheduledTime"],
            "checkin" => $checkInTime,
            "createdToday" => new DateTime($row["CreationDate"]) == new DateTime("midnight"),
            "referringPhysician" => $row["ReferringPhysician"],
            "mediStatus" => $row["MedivisitStatus"],
            "diagnosis" => $row["Diagnosis"],
        ];
   }
}

$listOfAppointments = utf8_encode_recursive($listOfAppointments);
echo json_encode($listOfAppointments);


exit;

?>
