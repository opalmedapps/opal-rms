<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a POST request containing Medivisit appointment information and inserts/updates the appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

#process post request
$postParams = getPostContents();

#currently, the params are sent with a single space if they are empty
#set these empty params to NULL
foreach($postParams as $param)
{
    if($param === ' ') {$param = NULL;}
}

#keep only the parameters we need
$appointmentInfo = [
    "ImportTimestamp"   => (new DateTime())->format("Y-m-d h:m:s"),
    "Result"            => NULL,
    "Action"            => !empty($postParams["Action"]) ? $postParams["Action"] : NULL,
    "AdmDesc"           => !empty($postParams["AdmDesc"]) ? $postParams["AdmDesc"] : NULL,
    "AdmType"           => !empty($postParams["AdmType"]) ? $postParams["AdmType"] : NULL,
    "AppointCode"       => !empty($postParams["AppointCode"]) ? $postParams["AppointCode"] : NULL,
    "AppointDate"       => !empty($postParams["AppointDate"]) ? $postParams["AppointDate"] : NULL,
    "AppointId"         => !empty($postParams["AppointId"]) ? $postParams["AppointId"] : NULL,
    "AppointSys"        => !empty($postParams["AppointSys"]) ? $postParams["AppointSys"] : NULL,
    "AppointTime"       => !empty($postParams["AppointTime"]) ? $postParams["AppointTime"] : NULL,
    "CreationDate"      => !empty($postParams["CreationDate"]) ? $postParams["CreationDate"] : NULL,
    "PatFirstName"      => !empty($postParams["PatFirstName"]) ? $postParams["PatFirstName"] : NULL,
    "PatLastName"       => !empty($postParams["PatLastName"]) ? $postParams["PatLastName"] : NULL,
    "PatientId"         => !empty($postParams["PatientId"]) ? $postParams["PatientId"] : NULL,
    "Ramq"              => !empty($postParams["Ramq"]) ? $postParams["Ramq"] : NULL,
    "RamqExpireDate"    => !empty($postParams["RamqExpireDate"]) ? $postParams["RamqExpireDate"] : NULL,
    "ReferringMd"       => !empty($postParams["ReferringMd"]) ? $postParams["ReferringMd"] : NULL,
    "ResourceCode"      => !empty($postParams["ResourceCode"]) ? $postParams["ResourceCode"] : NULL,
    "ResourceName"      => !empty($postParams["ResourceName"]) ? $postParams["ResourceName"] : NULL,
    "Site"              => !empty($postParams["Site"]) ? $postParams["Site"] : NULL,
    "Status"            => !empty($postParams["Status"]) ? $postParams["Status"] : NULL,
    "VisitDate"         => !empty($postParams["VisitDate"]) ? $postParams["VisitDate"] : NULL,
    "VisitId"           => !empty($postParams["VisitId"]) ? $postParams["VisitId"] : NULL,
    "VisitTime"         => !empty($postParams["VisitTime"]) ? $postParams["VisitTime"] : NULL,
];

#validate the inputs
list($validationResult,$appointmentInfoValidated) = validateAppointmentInfo($appointmentInfo);

if($validationResult !== 'Success')
{
    $appointmentInfo["Result"] = $validationResult;
    $logRequest($appointmentInfo);
    echo $validationResult;
    exit;
}

#
$patient = new Patient([
    "firstName" => $appointmentInfoValidated["PatFirstName"],
    "lastName" => $appointmentInfoValidated["PatLastName"],
    "patientId" => $appointmentInfoValidated["PatientId"],
    "ssn" => $appointmentInfoValidated["Ramq"],
    "ssnExpDate" => $appointmentInfoValidated["RamqExpireDate"]
]);

$appointment = new Appointment(
    [
        "appointmentCode" => $appointmentInfoValidated["AppointCode"],
        "creationDate" => $appointmentInfoValidated["CreationDate"],
        "id" => !empty($appointmentInfoValidated["AppointId"]) ? $appointmentInfoValidated["AppointId"] : $appointmentInfoValidated["VisitId"],
        "referringMd" => $appointmentInfoValidated["ReferringMd"],
        "resource" => $appointmentInfoValidated["ResourceCode"],
        "resourceDesc" => $appointmentInfoValidated["ResourceName"],
        "scheduledDate" => $appointmentInfoValidated["AppointDate"],
        "scheduledDateTime" => $appointmentInfoValidated["AppointDate"] ." ". $appointmentInfoValidated["AppointTime"],
        "scheduledTime" => $appointmentInfoValidated["AppointTime"],
        "site" => $appointmentInfoValidated["Site"],
        "status" => $appointmentInfoValidated["Status"],
        "sourceStatus" => $appointmentInfoValidated["AdmDesc"],
        "system" => "MEDIVISIT",
        "type" => !empty($appointmentInfoValidated["AppointId"]) ? "Appointment" : "Visit Add On"
    ],
    $patient
);



###################################
# Functions
###################################

#function to transform the information received from Medivisit into an ORMS compatible form
#takes in a reference to an
function validateAppointmentInfo(array &$appointment,array $appointmentInfoValidated): string
{


    return $appointment;
}

#
function logRequest(array $requestInfo)
{
    #log the request
    $dbh = Config::getDatabaseConnection("LOGS");
    $dbh->prepare("
        INSERT INTO MedivisitInterfaceEngineImportLog
        (ImportTimestamp,Result,Action,AdmDesc,AdmType,AppointCode,AppointDate,AppointId,AppointSys,AppointTime,CreationDate,PatFirstName,PatLastName,PatientId,Ramq,RamqExpireDate,ReferringMd,ResourceCode,ResourceName,Site,Status,VisitDate,VisitId,VisitTime)
        VALUES
        (:ImportTimestamp,:Result,:Action,:AdmDesc,:AdmType,:AppointCode,:AppointDate,:AppointId,:AppointSys,:AppointTime,:CreationDate,:PatFirstName,:PatLastName,:PatientId,:Ramq,:RamqExpireDate,:ReferringMd,:ResourceCode,:ResourceName,:Site,:Status,:VisitDate,:VisitId,:VisitTime)"
    );
    $dbh->execute($requestInfo);
}

function sendEmail(array $requestInfo)
{

}

?>