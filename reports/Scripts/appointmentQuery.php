<?php declare(strict_types = 1);

// legacy report script refactored from perl

#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the database.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Config;

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
$clinic         = $_GET["clinic"];
$appType        = $_GET["type"];
$specificType   = $_GET["specificType"];
$updateRamq     = $_GET["updateRamq"];
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

$specificType = preg_replace("/'/","\'",$specificType);

$specialityFilter = "AND CR.Speciality = '$clinic' ";

if($appType === "specific") {
    $typeFilter = "AND MV.ResourceDescription = '$specificType' " ;
}
else {
    $typeFilter = "";
}

$dateFormat = '%Y-%m-%d %H:%M:%S';

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
$dbh = Config::getDatabaseConnection("ORMS");

$queryClinics = $dbh->prepare("
    SELECT DISTINCT
        MV.ResourceDescription,
        MV.Resource
    FROM
        MediVisitAppointmentList MV,
        ClinicResources CR
    WHERE
        MV.ClinicResourcesSerNum = CR.ClinicResourcesSerNum
        $specialityFilter
    ORDER BY
        MV.ResourceDescription,
        MV.Resource
");
$queryClinics->execute();

$clinics = array_map(function($x) {
    return [
        "name"  =>  $x["ResourceDescription"],
        "resources" => $x["Resource"]
    ];
},$queryClinics->fetchAll());


$queryAppointments = $dbh->prepare("
    SELECT
        MV.AppointmentSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        Patient.SSN,
        Patient.SSNExpDate,
        MV.ResourceDescription,
        MV.Resource,
        MV.AppointmentCode,
        MV.Status,
        MV.ScheduledDate AS ScheduledDate,
        MV.ScheduledTime AS ScheduledTime,
        MV.CreationDate,
        MV.ReferringPhysician,
        (select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePL,
        (select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePLM,
        MV.MedivisitStatus
    FROM
        Patient,
        MediVisitAppointmentList MV
    WHERE
        Patient.PatientSerNum = MV.PatientSerNum
        AND Patient.PatientId != '9999996'
        AND Patient.PatientId != '9999998'
        AND MV.ResourceDescription in (Select distinct CR.ResourceName from ClinicResources CR Where trim(CR.ResourceName) not in ('', 'null') $specialityFilter)
        $appFilter
        AND MV.Status != 'Deleted'
        AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
        $typeFilter
    ORDER BY ScheduledDate,ScheduledTime
");
$queryAppointments->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate
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

    $creationDate = new DateTime($x["CreationDate"]);
    $today = new DateTime();

    return [
        "fname"                 => $x["FirstName"],
        "lname"                 => $x["LastName"],
        "pID"                   => $x["PatientId"],
        "ssn"                   => [
            "num"                   => $x["SSN"],
            "expDate"               => (strlen($x["SSNExpDate"]) === 3) ? "0$x[SSNExpDate]" : $x["SSNExpDate"],
            "expired"               => 1,
        ],
        "appName"               => $x["ResourceDescription"],
        "appClinic"             => $x["Resource"],
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

//script originally updated the patients ramq in the db if it was expired
//no one is using this functionality atm so the original code (in perl) has been commented out

    // #check if the ssn is expired
    // my $expired = 1;

    // #get information from the hospital ADT for the ramq if enabled
    // #also update expired ramqs if any are found
    // if($updateRamq)
    // {
    //     my $currentDate  = localtime;

    //     #check if the ramq is still valid before proceeding
    //     #this is to reduce computation time
    //     if(length($ssnExp{$ser}) eq 4 and substr($ssnExp{$ser},-2) <= 12)
    //     {
    //         my $expTP = Time::Piece->strptime("20$ssnExp{$ser}","%Y%m");
    //         $expTP = $expTP + Time::Piece->ONE_MONTH;

    //         $expired = 0 if($expTP > $currentDate);
    //     }

    //     if($expired eq 1)
    //     {
    //         my $ramqInfo = HospitalADT->getRamqInformation($ssn{$ser});

    //         if($ramqInfo->{'Status'} =~ /Valid/)
    //         {
    //             $expired = 0;

    //             #also make sure the ramq in the WRM db is up to date if the ramq is expired in the db
    //             HospitalADT->updateRamqInWRM($ssn{$ser});
    //         }
    //     }
    // }

$clinics = utf8_encode_recursive($clinics);
$appointments = utf8_encode_recursive($appointments);

echo json_encode([
    "clinics"       => $clinics,
    "tableData"     => $appointments
]);

?>
