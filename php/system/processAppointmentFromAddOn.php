<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a POST request from the add on page and inserts/updates the appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode("Non POST requests not supported");
    exit;
}

#process post request
$postParams = getPostContents();
$postParams = utf8_decode_recursive($postData);

#keep only the parameters we need
$appointmentInfo = [
    "ImportTimestamp"   => (new DateTime())->format("Y-m-d H:i:s"),
    "Result"            => NULL,
    "AppointCode"       => !empty($postParams["AppointCode"]) ? $postParams["AppointCode"] : NULL,
    "AppointDate"       => !empty($postParams["AppointDate"]) ? $postParams["AppointDate"] : NULL,
    "AppointTime"       => !empty($postParams["AppointTime"]) ? $postParams["AppointTime"] : NULL,
    "PatFirstName"      => !empty($postParams["PatFirstName"]) ? $postParams["PatFirstName"] : NULL,
    "PatLastName"       => !empty($postParams["PatLastName"]) ? $postParams["PatLastName"] : NULL,
    "PatientId"         => !empty($postParams["PatientId"]) ? $postParams["PatientId"] : NULL,
    "Ramq"              => !empty($postParams["Ramq"]) ? $postParams["Ramq"] : NULL,
    "RamqExpireDate"    => !empty($postParams["RamqExpireDate"]) ? $postParams["RamqExpireDate"] : NULL,
    "ResourceCode"      => !empty($postParams["ResourceCode"]) ? $postParams["ResourceCode"] : NULL,
    "ResourceName"      => !empty($postParams["ResourceName"]) ? $postParams["ResourceName"] : NULL,
    "Site"              => !empty($postParams["Site"]) ? $postParams["Site"] : NULL,
    "SpecialityCode"    => !empty($postParams["SpecialityCode"]) ? $postParams["SpecialityCode"] : NULL
];

try
{
    #validate the inputs
    $appointmentInfoValidated = validateAppointmentInfo($appointmentInfo);

    #instantiate an appointment object including a patient object
    $appointment = new Appointment(
        [
            "appointmentCode"   => $appointmentInfoValidated["AppointCode"],
            "creationDate"      => (new DateTime())->format("Y-m-d"),
            "id"                => "$appointmentInfoValidated[Site]$appointmentInfoValidated[PatientId]-$appointmentInfoValidated[ImportTimestamp]",
            "referringMd"       => NULL,
            "resource"          => $appointmentInfoValidated["ResourceCode"],
            "resourceDesc"      => $appointmentInfoValidated["ResourceName"],
            "scheduledDate"     => $appointmentInfoValidated["AppointDate"],
            "scheduledDateTime" => $appointmentInfoValidated["AppointDate"] ." ". $appointmentInfoValidated["AppointTime"],
            "scheduledTime"     => $appointmentInfoValidated["AppointTime"],
            "site"              => $appointmentInfoValidated["Site"],
            "speciality"        => $appointmentInfoValidated["SpecialityCode"],
            "status"            => "Open",
            "sourceStatus"      => NULL,
            "system"            => "InstantAddOn"
        ],
        new Patient([
            "firstName"     => $appointmentInfoValidated["PatFirstName"],
            "lastName"      => $appointmentInfoValidated["PatLastName"],
            "patientId"     => $appointmentInfoValidated["PatientId"],
            "ssn"           => $appointmentInfoValidated["Ramq"],
            "ssnExpDate"    => $appointmentInfoValidated["RamqExpireDate"]
        ])
    );

    #insert the appointment in the database
    #if the appointment exists, it will be updated instead
    $result = $appointment->insertOrUpdateAppointmentInDatabase();

    if($result === TRUE)
    {
        $appointmentInfo["Result"] = "Success";
        checkInPatientForAddOn($appointmentInfo["PatientId"]);
    }
    else
    {
        $appointmentInfo["Result"] = "Appointment insert or update failed";
    }
}
catch (Exception $e)
{
    $appointmentInfo["Result"] = $e->getMessage();
}
finally
{
    echo "Add on for $appointmentInfo[PatFirstName], $appointmentInfo[PatLastName] ($appointmentInfo[PatientId]) : $appointmentInfo[Result]";
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
        if(ctype_space($param)) {
            $param = NULL;
        }
    }

    #ssn expiration dates come in as YYMM for quebec ramqs
    #other formats may come in as YYMMDD
    #just take the first 4 characters
    $appInfo["RamqExpireDate"] = substr($appInfo["RamqExpireDate"],0,4);

    #time might enter without seconds so add them
    $appInfo["AppointTime"] = substr($appInfo["AppointTime"],0,5);
    $appInfo["AppointTime"] .= ":00";

    return $appInfo;
}

#temp function to check in a patient after their add on has been created
function checkInPatientForAddOn(string $patId): void
{
    $path = Config::getConfigs("path");
    $sciptLocation = $path["BASE_URL"] ."/php/system/checkInPatientAriaMedi.php?CheckinVenue=ADDED ON BY RECEPTION&PatientId=$patId";
    $sciptLocation = str_replace(' ','%20',$sciptLocation);
    file($sciptLocation);
}

#logs the POST request
function logRequest(array $requestInfo): void
{
    if($requestInfo["Result"] === NULL) {
        $requestInfo["Result"] = "System Error";
    }

    #log the request
    $dbh = Config::getDatabaseConnection("LOGS");
    $query = $dbh->prepare("
        INSERT INTO ImportLogForAddOns
        (ImportTimestamp,Result,AppointCode,AppointDate,AppointTime,PatFirstName,PatLastName,PatientId,Ramq,RamqExpireDate,ResourceCode,ResourceName,Site,SpecialityCode)
        VALUES
        (:ImportTimestamp,:Result,:AppointCode,:AppointDate,:AppointTime,:PatFirstName,:PatLastName,:PatientId,:Ramq,:RamqExpireDate,:ResourceCode,:ResourceName,:Site,:SpecialityCode)"
    );
    $query->execute($requestInfo);
}

?>
