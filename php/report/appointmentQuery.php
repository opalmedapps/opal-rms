<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

// legacy report script refactored from perl

//---------------------------------------------------------------------------------------------------------------
// This script finds all appointments matching the specified criteria and returns patient information from the database.
//---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\DataAccess\ReportAccess;
use Orms\DateTime;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$sDateInit      = $params["sDate"];
$eDateInit      = $params["eDate"];
$sTime          = $params["sTime"];
$eTime          = $params["eTime"];
$comp           = $params["comp"];
$open           = $params["openn"];
$canc           = $params["canc"];
$arrived        = $params["arrived"];
$notArrived     = $params["notArrived"];
$speciality     = $params["speciality"];
$appType        = $params["type"];
$specificType   = $params["specificType"] ?? null;
$mediAbsent     = $params["mediAbsent"];
$mediAddOn      = $params["mediAddOn"];
$mediCancelled  = $params["mediCancelled"];
$mediPresent    = $params["mediPresent"];

$sDate = DateTime::createFromFormatN("Y-m-d H:i", "$sDateInit $sTime") ?? throw new Exception("Invalid datetime");
$eDate = DateTime::createFromFormatN("Y-m-d H:i", "$eDateInit $eTime") ?? throw new Exception("Invalid datetime");

$statusFilter = [];
if($comp) $statusFilter[] = "Completed";
if($open) $statusFilter[] = "Open";
if($canc) $statusFilter[] = "Cancelled";

$codeFilter = [];
if($appType === "specific" && $specificType !== null) {
    $codeFilter = [$specificType];
}
$codeFilter = Encoding::utf8_decode_recursive($codeFilter);

$clinics = AppointmentInterface::getClinicCodes((int) $speciality);

//filter appointments depending on input parameters
$appointments = ReportAccess::getListOfAppointmentsInDateRange($sDate, $eDate, (int) $speciality, $statusFilter, $codeFilter, []);

$appointments = array_filter($appointments, function($x) use ($arrived, $notArrived, $mediAbsent, $mediAddOn, $mediCancelled, $mediPresent) {
    /** @psalm-suppress RedundantCondition */
    if(
        ($arrived && !$notArrived && $x["createdToday"])
        || (!$arrived && $notArrived && !$x["createdToday"])
        || ($arrived && $notArrived)
    ) {
        $valid = true;
    }
    else {
        $valid = false;
    }

    if(preg_match("/^999999/",$x["mrn"]) === 1) {
        $valid = false;
    }

    if(!$mediAbsent && preg_match("/Absent/", $x["mediStatus"] ?? ""))            $valid = false;
    elseif(!$mediAddOn && preg_match("/Add-on/", $x["mediStatus"] ?? ""))         $valid = false;
    elseif(!$mediCancelled && preg_match("/Cancelled/", $x["mediStatus"] ?? ""))  $valid = false;
    elseif(!$mediPresent && preg_match("/Pr.sent/", $x["mediStatus"] ?? ""))      $valid = false;

    return $valid;
});

$clinics = Encoding::utf8_encode_recursive($clinics);
$appointments = Encoding::utf8_encode_recursive($appointments);

echo json_encode([
    "clinics"       => $clinics,
    "tableData"     => $appointments
]);
