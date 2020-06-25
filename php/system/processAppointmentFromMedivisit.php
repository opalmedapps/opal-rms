<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a POST request containing Medivisit appointment information and inserts/updates the appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode("Non POST requests not supported");
    exit;
}

#process post request
$postParams = getPostContents();
$postParams = utf8_decode_recursive($postParams);

#keep only the parameters we need
$appointmentInfo = [
    "ImportTimestamp"   => (new DateTime())->format("Y-m-d H:i:s"),
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
    "SpecialityGroup"    => !empty($postParams["SpecialityGroup"]) ? $postParams["SpecialityGroup"] : NULL,
    "Status"            => !empty($postParams["Status"]) ? $postParams["Status"] : NULL,
    "VisitDate"         => !empty($postParams["VisitDate"]) ? $postParams["VisitDate"] : NULL,
    "VisitId"           => !empty($postParams["VisitId"]) ? $postParams["VisitId"] : NULL,
    "VisitTime"         => !empty($postParams["VisitTime"]) ? $postParams["VisitTime"] : NULL,
];

try
{
    #validate the inputs
    $appointmentInfoValidated = validateAppointmentInfo($appointmentInfo);

    #instantiate an appointment object including a patient object
    $appointment = new Appointment(
        [
            "appointmentCode"   => $appointmentInfoValidated["AppointCode"],
            "creationDate"      => $appointmentInfoValidated["CreationDate"],
            "id"                => !empty($appointmentInfoValidated["AppointId"]) ? $appointmentInfoValidated["AppointId"] : $appointmentInfoValidated["VisitId"],
            "referringMd"       => $appointmentInfoValidated["ReferringMd"],
            "resource"          => $appointmentInfoValidated["ResourceCode"],
            "resourceDesc"      => $appointmentInfoValidated["ResourceName"],
            "scheduledDate"     => $appointmentInfoValidated["AppointDate"],
            "scheduledDateTime" => $appointmentInfoValidated["AppointDate"] ." ". $appointmentInfoValidated["AppointTime"],
            "scheduledTime"     => $appointmentInfoValidated["AppointTime"],
            "site"              => $appointmentInfoValidated["Site"],
            "specialityGroup"   => $appointmentInfoValidated["SpecialityGroup"],
            "status"            => $appointmentInfoValidated["Status"],
            "sourceStatus"      => $appointmentInfoValidated["AdmDesc"],
            "system"            => $appointmentInfoValidated["AppointSys"] ?? "MEDIVISIT"
        ],
        new Patient([
            "firstName"     => $appointmentInfoValidated["PatFirstName"],
            "lastName"      => $appointmentInfoValidated["PatLastName"],
            "patientId"     => $appointmentInfoValidated["PatientId"],
            "ssn"           => $appointmentInfoValidated["Ramq"],
            "ssnExpDate"    => $appointmentInfoValidated["RamqExpireDate"]
        ])
    );

    #when an appointment is cancelled, we don't get the appointment id of the cancelled appointment but rather a new appointment id corresponding to the cancellation
    #since we don't have the original appointment id, we delete all appointments with the same resource and scheduled time for that patient
    if($appointmentInfo["Action"] === "S15") $appointment->deleteSimilarAppointments();

    #insert the appointment in the database
    #if the appointment exists, it will be updated instead
    $result = $appointment->insertOrUpdateAppointmentInDatabase();

    if($result === TRUE)
    {
        $appointmentInfo["Result"] = "Success";
        http_response_code(200);
    }
    else
    {
        $appointmentInfo["Result"] = "Appointment insert or update failed";
        http_response_code(400);
    }
}
catch (Exception $e)
{
    $appointmentInfo["Result"] = $e->getMessage();
    http_response_code(400);
}
finally
{
    echo $appointmentInfo["Result"];
    logRequest($appointmentInfo);
}

exit;

###################################
# Functions
###################################

#function to transform the information received from Medivisit into an ORMS compatible form
function validateAppointmentInfo(array $appInfo): array
{
    #if a param is just whitespace, set it to null
    foreach($appInfo as &$param) {
        if(ctype_space($param)) $param = NULL;
    }

    #check if the action in Medivisit is a recognized one
    if(!preg_match("/^(S12|S14|S15|S17)$/",$appInfo["Action"])) throw new Exception("Unknown action");

    #dates come in as YYYY/MM/DD
    #change '/' to '-'
    $appInfo["AppointDate"] = str_replace("/","-",$appInfo["AppointDate"]);
    $appInfo["VisitDate"] = str_replace("/","-",$appInfo["VisitDate"]);
    $appInfo["CreationDate"] = str_replace("/","-",$appInfo["CreationDate"]);

    #time comes in as HH:MM
    #add seconds to time
    if(!empty($appInfo["AppointTime"])) $appInfo["AppointTime"] .= ":00";
    if(!empty($appInfo["VisitTime"])) $appInfo["VisitTime"] .= ":00";
    if(!empty($appInfo["CreationDate"])) $appInfo["CreationDate"] .= ":00";

    #ssn expiration dates come in as YYMM for quebec ramqs
    #other formats may come in as YYMMDD
    #just take the first 4 characters
    $appInfo["RamqExpireDate"] = substr($appInfo["RamqExpireDate"],0,4);

    #if there is no ramq, use the patient mrn
    if(empty($appInfo["Ramq"])) $appInfo["Ramq"] = $appInfo["PatientId"];

    #convert an 'In Progress' status to 'Open'
    if($appInfo["Status"] === "In Progress") $appInfo["Status"] = "Open";

    #if the Action is a S17, the appointment is a deleted one
    if($appInfo["Action"] === "S17") $appInfo["Status"] = "Deleted";

    return $appInfo;
}

#logs the POST request
function logRequest(array $requestInfo): void
{
    if($requestInfo["Result"] === NULL) $requestInfo["Result"] = "System Error";

    #log the request
    $dbh = Config::getDatabaseConnection("LOGS");
    $query = $dbh->prepare("
        INSERT INTO ImportLogForMedivisitInterfaceEngine
        (ImportTimestamp,Result,Action,AdmDesc,AdmType,AppointCode,AppointDate,AppointId,AppointSys,AppointTime,CreationDate,PatFirstName,PatLastName,PatientId,Ramq,RamqExpireDate,ReferringMd,ResourceCode,ResourceName,Site,SpecialityGroup,Status,VisitDate,VisitId,VisitTime)
        VALUES
        (:ImportTimestamp,:Result,:Action,:AdmDesc,:AdmType,:AppointCode,:AppointDate,:AppointId,:AppointSys,:AppointTime,:CreationDate,:PatFirstName,:PatLastName,:PatientId,:Ramq,:RamqExpireDate,:ReferringMd,:ResourceCode,:ResourceName,:Site,:SpecialityGroup,:Status,:VisitDate,:VisitId,:VisitTime)"
    );
    $query->execute($requestInfo);

    #send out an email if there was an error
    // if(!preg_match("/^(Success|Incorrect date format)$/",$requestInfo["Result"])) sendEmail($requestInfo);
}

#sends an email to the orms admin
function sendEmail(array $requestInfo): void
{

    $recepient = "victor.matassa@muhc.mcgill.ca";
    $subject = "ORMS Appointment Error";
    $message = "ORMS Appointment Error - $requestInfo[Result] for appointment id '$requestInfo[AppointId]' or visit id '$requestInfo[VisitId]' at time [$requestInfo[ImportTimestamp]]";
    $headers = [
        "From" => "orms@muhc.mcgill.ca"
    ];

    mail($recepient,$subject,$message,$headers);
}

?>