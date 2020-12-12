<?php

// Get Patient ID
$wsPatientID = filter_var($_GET['ID'], FILTER_SANITIZE_STRING);
// Exit if Patient ID is empty
if (strlen(trim($wsPatientID)) == 0) {
    die;
}

// Get Report ID
$wsReportID = filter_var($_GET['rptID'], FILTER_SANITIZE_STRING);
// Exit if Report ID is empty
if (strlen(trim($wsReportID)) == 0) {
    die;
}

// Get Export Flag
//$wsExportFlag = filter_var($_GET['efID'], FILTER_SANITIZE_STRING);
$wsExportFlag = 0;
// Set value to 0 if Export Flag is empty
if (strlen(trim($wsExportFlag)) == 0) {
    $wsExportFlag=0;
}

// generate a non chart report
include "reportDisplayNonChart.php";

?>
