<?php declare(strict_types = 1);

// legacy report script refactored from perl

#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the database.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

#------------------------------------------
#parse input parameters
#------------------------------------------
$sDateInit      = $_GET["sDate"];
$eDateInit      = $_GET["eDate"];
$sTime          = $_GET["sTime"];
$eTime          = $_GET["eTime"];
$comp           = $_GET["comp"];
$open           = $_GET["openn"];
$prog           = $_GET['prog'];
$canc           = $_GET["canc"];
$arrived        = $_GET["arrived"];
$notArrived     = $_GET["notArrived"];
$speciality     = $_GET["speciality"];
$appType        = $_GET["type"];
$specificType   = $_GET["specificType"] ?? NULL;
$mediAbsent     = $_GET["mediAbsent"];
$mediAddOn      = $_GET["mediAddOn"];
$mediCancelled  = $_GET["mediCancelled"];
$mediPresent    = $_GET["mediPresent"];

$sDate = $sDateInit ." $sTime";
$eDate = $eDateInit ." $eTime";

$appFilter = "";
$appFilter .= "AND ( ";
if($comp) $appFilter .= "MV.Status = 'Completed' OR ";
if($open) $appFilter .= "MV.Status = 'Open' OR ";
if($canc) $appFilter .= "MV.Status = 'Cancelled' OR ";
if($prog) $appFilter .= "MV.Status = 'In Progress' OR ";
$appFilter = substr($appFilter,0,-4);
$appFilter .=  ") ";

if($appType === "specific" && $specificType !== NULL) {
    $specificType = preg_replace("/'/","\'",$specificType);
    $typeFilter = "AND CR.ResourceName = '$specificType' " ;
}
else {
    $typeFilter = "";
}

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
$dbh = Database::getOrmsConnection();

$queryClinics = $dbh->prepare("
    SELECT DISTINCT
        ResourceName,
        ResourceCode
    FROM
        ClinicResources
    WHERE
        SpecialityGroupId = ?
    ORDER BY
        ResourceName,
        ResourceCode
");
$queryClinics->execute([$speciality]);

$clinics = array_map(function($x) {
    return [
        "name"      => $x["ResourceName"],
        "resources" => $x["ResourceCode"]
    ];
},$queryClinics->fetchAll());


$queryAppointments = $dbh->prepare("
    SELECT
        MV.AppointmentSerNum,
        P.FirstName,
        P.LastName,
        PH.MedicalRecordNumber,
        H.HospitalCode,
        I.InsuranceCode,
        PI.InsuranceNumber,
        PI.ExpirationDate,
        CR.ResourceName,
        CR.ResourceCode,
        AC.AppointmentCode,
        MV.Status,
        MV.ScheduledDate AS ScheduledDate,
        MV.ScheduledTime AS ScheduledTime,
        MV.CreationDate,
        MV.ReferringPhysician,
        (select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePL,
        (select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePLM,
        MV.MedivisitStatus
    FROM
        Patient P
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
            AND MV.Status != 'Deleted'
            AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
            $appFilter
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            $typeFilter
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
            AND SG.SpecialityGroupId = :spec
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            AND PH.HospitalId = SG.HospitalId
            AND PH.Active = 1
        INNER JOIN Hospital H ON H.HospitalId = SG.HospitalId
        LEFT JOIN Insurance I ON I.InsuranceCode = 'RAMQ'
        LEFT JOIN PatientInsuranceIdentifier PI ON PI.InsuranceId = I.InsuranceId
    WHERE
        P.PatientId NOT LIKE '999999%'
    ORDER BY ScheduledDate,ScheduledTime
");
$queryAppointments->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate,
    ":spec"  => $speciality
]);

$appointments = $queryAppointments->fetchAll();

//filter appointments depending on input parameters
$appointments = array_filter($appointments,function($x) use ($arrived,$notArrived,$mediAbsent,$mediAddOn,$mediCancelled,$mediPresent) {
    if(
        ($arrived && !$notArrived && ($x["ArrivalDateTimePL"] ?? $x["ArrivalDateTimePLM"]))
        || (!$arrived && $notArrived && !($x["ArrivalDateTimePL"] ?? $x["ArrivalDateTimePLM"]))
        || ($arrived && $notArrived)
    ) {
        $valid = TRUE;
    }
    else {
        $valid = FALSE;
    }

    if(!$mediAbsent && preg_match("/Absent/",$x["MedivisitStatus"]))            $valid = FALSE;
    elseif(!$mediAddOn && preg_match("/Add-on/",$x["MedivisitStatus"]))         $valid = FALSE;
    elseif(!$mediCancelled && preg_match("/Cancelled/",$x["MedivisitStatus"]))  $valid = FALSE;
    elseif(!$mediPresent && preg_match("/Pr.sent/",$x["MedivisitStatus"]))      $valid = FALSE;

    return $valid;
});

$appointments = array_map(function($x) {
    return [
        "fname"                 => $x["FirstName"],
        "lname"                 => $x["LastName"],
        "mrn"                   => $x["MedicalRecordNumber"],
        "site"                  => $x["HospitalCode"],
        "ramq"                  => [
            "num"                   => $x["InsuranceNumber"],
            "expDate"               => $x["ExpirationDate"],
        ],
        "appName"               => $x["ResourceName"],
        "appClinic"             => $x["ResourceCode"],
        "appType"               => $x["AppointmentCode"],
        "appStatus"             => $x["Status"],
        "appDay"                => $x["ScheduledDate"],
        "appTime"               => substr($x["ScheduledTime"],0,-3),
        "checkin"               => $x["ArrivalDateTimePL"] ?? $x["ArrivalDateTimePLM"],
        "createdToday"          => (new DateTime($x["CreationDate"]))->format("Y-m-d") === (new DateTime($x["ScheduledDate"]))->format("Y-m-d"), //"today" refers to the date of the appointment
        "referringPhysician"    => $x["ReferringPhysician"],
        "mediStatus"            => $x["MedivisitStatus"],
    ];
},$appointments);

$clinics = Encoding::utf8_encode_recursive($clinics);
$appointments = Encoding::utf8_encode_recursive($appointments);

echo json_encode([
    "clinics"       => $clinics,
    "tableData"     => $appointments
]);
