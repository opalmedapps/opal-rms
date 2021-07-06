<?php declare(strict_types = 1);

// legacy report script refactored from perl

#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the database.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DateTime;
use Orms\DataAccess\ReportAccess;

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

$sDate = DateTime::createFromFormatN("Y-m-d H:i","$sDateInit $sTime") ?? throw new Exception("Invalid datetime");
$eDate = DateTime::createFromFormatN("Y-m-d H:i","$eDateInit $eTime") ?? throw new Exception("Invalid datetime");

$statusFilter = [];
if($comp) $statusFilter[] = "Completed";
if($open) $statusFilter[] = "Open";
if($canc) $statusFilter[] = "Cancelled";
if($prog) $statusFilter[] = "In Progress";

$codeFilter = [];
if($appType === "specific" && $specificType !== NULL) {
    $codeFilter = [$specificType];
}

$clinics = ReportAccess::getClinicCodes((int) $speciality);

//filter appointments depending on input parameters
$appointments = ReportAccess::getListOfAppointmentsInDateRange($sDate,$eDate,(int) $speciality,$statusFilter,$codeFilter);

$appointments = array_filter($appointments,function($x) use ($arrived,$notArrived,$mediAbsent,$mediAddOn,$mediCancelled,$mediPresent) {
    if(
        ($arrived && !$notArrived && $x["createdToday"])
        || (!$arrived && $notArrived && !$x["createdToday"])
        || ($arrived && $notArrived)
    ) {
        $valid = TRUE;
    }
    else {
        $valid = FALSE;
    }

    if(!$mediAbsent && preg_match("/Absent/",$x["mediStatus"]))            $valid = FALSE;
    elseif(!$mediAddOn && preg_match("/Add-on/",$x["mediStatus"]))         $valid = FALSE;
    elseif(!$mediCancelled && preg_match("/Cancelled/",$x["mediStatus"]))  $valid = FALSE;
    elseif(!$mediPresent && preg_match("/Pr.sent/",$x["mediStatus"]))      $valid = FALSE;

    return $valid;
});

$clinics = Encoding::utf8_encode_recursive($clinics);
$appointments = Encoding::utf8_encode_recursive($appointments);

echo json_encode([
    "clinics"       => $clinics,
    "tableData"     => $appointments
]);
