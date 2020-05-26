<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a csv file generated from Impromptu containing Medivisit appointment information and inserts/updates the appointment in the ORMS database
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

#get csv file name from command line arguments
$csvFile = (getopt(null,["file:"]))["file"];

#csv file must have been created today, otherwise send an error
#however, if its the weekend, don't send an error
$modDate = (new DateTime())->setTimestamp(filemtime($csvFile))->format("Y-m-d");
$today = (new DateTime())->format("Y-m-d");

// if($modDate !== $today)
// {
//     if(date('D') == 'Sat' || date('D') == 'Sun') {
//         exit;
//     }
//     else {
//         throw new Exception("CSV file was not updated today");
//     }
// }

$fileHandle = fopen($csvFile,"r");

if($fileHandle !== FALSE) {
    processCsvFile($fileHandle);
}
else {
    throw new Exception("Error opening file");
}

exit;

###################################
# Functions
###################################

#takes a just opened file handle for a csv file and inserts all the appointments within into the ORMS db
function processCsvFile($handle): void #$handle is stream
{
    $headers = fgetcsv($handle);

    #csv file is encoded in iso-8859-1 so we need to change it to utf8
    $headers = array_map('utf8_encode',$headers);

    while(($row = fgetcsv($handle)) !== FALSE)
    {
        $importedAppointment = array_combine($headers,$row);
        processAppointment($importedAppointment);
    }
}

function processAppointment(array $appParams): void
{
    #keep only the parameters we need
    // $appointmentInfo = [
    //     "ImportTimestamp"   => (new DateTime())->format("Y-m-d H:i:s"),
    //     "Result"            => NULL,
    //     "ActivityCode"      => !empty($appParams["Activity Code"]) ? $appParams["Activity Code"] : NULL,
    //     "AppIDComb"         => !empty($appParams["AppIDComb"]) ? $appParams["AppIDComb"] : NULL,
    //     "AppDate"           => !empty($appParams["App Date"]) ? $appParams["App Date"] : NULL,
    //     "AppTime"           => !empty($appParams["App Time"]) ? $appParams["App Time"] : NULL,
    //     "DateExpRAMQ"       => !empty($appParams["Date Exp RAMQ"]) ? $appParams["Date Exp RAMQ"] : NULL,
    //     "DHCreRV"           => !empty($appParams["DH Cré RV"]) ? $appParams["DH Cré RV"] : NULL,
    //     "FirstName"         => !empty($appParams["First Name"]) ? $appParams["First Name"] : NULL,
    //     "Lastname"          => !empty($appParams["Last name"]) ? $appParams["Last name"] : NULL,
    //     "MdReferantRV"      => !empty($appParams["Md Référant RV"]) ? $appParams["Md Référant RV"] : NULL,
    //     "MRN"               => !empty($appParams["MRN"]) ? $appParams["MRN"] : NULL,
    //     "RAMQ"              => !empty($appParams["RAMQ"]) ? $appParams["RAMQ"] : NULL,
    //     "Resource"          => !empty($appParams["Resource"]) ? $appParams["Resource"] : NULL,
    //     "ResourceDes"       => !empty($appParams["Resource Des"]) ? $appParams["Resource Des"] : NULL,
    //     "Site"              => !empty($appParams["Site"]) ? $appParams["Site"] : NULL,
    // ];
    $appointmentInfo = [
        "ImportTimestamp"   => (new DateTime())->format("Y-m-d H:i:s"),
        "Result"            => NULL,
        "ActivityCode"      => !empty($appParams["App. Type"]) ? $appParams["App. Type"] : NULL,
        "AppIDComb"         => !empty($appParams["AppIDComb"]) ? $appParams["AppIDComb"] : NULL,
        "AppDate"           => !empty($appParams["App. Date"]) ? $appParams["App. Date"] : NULL,
        "AppTime"           => !empty($appParams["App Time"]) ? $appParams["App Time"] : NULL,
        "DateExpRAMQ"       => !empty($appParams["Date Exp"]) ? $appParams["Date Exp"] : NULL,
        "DHCreRV"           => !empty($appParams["Creation Date"]) ? $appParams["Creation Date"] : NULL,
        "FirstName"         => !empty($appParams["First Name"]) ? $appParams["First Name"] : NULL,
        "Lastname"          => !empty($appParams["Last Name"]) ? $appParams["Last Name"] : NULL,
        "MdReferantRV"      => !empty($appParams["Ref. MD"]) ? $appParams["Ref. MD"] : NULL,
        "MRN"               => !empty($appParams["MRN"]) ? $appParams["MRN"] : NULL,
        "RAMQ"              => !empty($appParams["RAMQ"]) ? $appParams["RAMQ"] : NULL,
        "Resource"          => !empty($appParams["Clinic"]) ? $appParams["Clinic"] : NULL,
        "ResourceDes"       => !empty($appParams["Clinic Desc."]) ? $appParams["Clinic Desc."] : NULL,
        "Site"              => "RVH",
        "Speciality"        => !empty($appParams["Speciality"]) ? $appParams["Speciality"]: NULL
    ];

    try
    {
        #validate the inputs
        $appointmentInfoValidated = validateAppointmentInfo($appointmentInfo);

        #instantiate an appointment object including a patient object
        $appointment = new Appointment(
            [
                "appointmentCode"   => $appointmentInfoValidated["ActivityCode"],
                "creationDate"      => $appointmentInfoValidated["DHCreRV"],
                "id"                => $appointmentInfoValidated["AppIDComb"],
                "referringMd"       => $appointmentInfoValidated["MdReferantRV"],
                "resource"          => $appointmentInfoValidated["Resource"],
                "resourceDesc"      => $appointmentInfoValidated["ResourceDes"],
                "scheduledDate"     => $appointmentInfoValidated["AppDate"],
                "scheduledDateTime" => $appointmentInfoValidated["AppDate"] ." ". $appointmentInfoValidated["AppTime"],
                "scheduledTime"     => $appointmentInfoValidated["AppTime"],
                "site"              => $appointmentInfoValidated["Site"],
                "speciality"        => $appointmentInfoValidated["Speciality"],
                "status"            => "Open",
                "sourceStatus"      => NULL,
                "system"            => "IMPROMPTU"
            ],
            new Patient([
                "firstName"     => $appointmentInfoValidated["FirstName"],
                "lastName"      => $appointmentInfoValidated["Lastname"],
                "patientId"     => $appointmentInfoValidated["MRN"],
                "ssn"           => $appointmentInfoValidated["RAMQ"],
                "ssnExpDate"    => $appointmentInfoValidated["DateExpRAMQ"]
            ])
        );

        #insert the appointment in the database
        #if the appointment exists, it will be updated instead
        $result = $appointment->insertOrUpdateAppointmentInDatabase();

        $appointmentInfo["Result"] = $result === TRUE ? "Success" : "Appointment insert or update failed";
    }
    catch (Exception $e)
    {
        $appointmentInfo["Result"] = $e->getMessage();
    }
    finally
    {
        logRequest($appointmentInfo);
    }
}

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
    $appInfo["DateExpRAMQ"] = substr($appInfo["DateExpRAMQ"],0,4) ?: "0000";

    #if there is no ramq, use the patient mrn
    if(empty($appInfo["RAMQ"])) $appInfo["RAMQ"] = $appInfo["MRN"];

    #site comes in as 'V' or 'G'
    #change to 'RVH' or 'MGH'
    $appInfo["Site"] = preg_replace(["/^V$/","/^G$/"],["RVH","MGH"],$appInfo["Site"]);

    #format dates
    $appInfo["AppDate"] = (new DateTime($appInfo["AppDate"]))->format("Y-m-d");
    $appInfo["DHCreRV"] = (new DateTime($appInfo["DHCreRV"]))->format("Y-m-d H:i:s");

    $appInfo["AppTime"] = (new DateTime($appInfo["AppTime"]))->format("H:i:s");

    return $appInfo;
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
        INSERT INTO ImportLogForImpromptu
        (ImportTimestamp,Result,ActivityCode,AppIDComb,AppDate,AppTime,DateExpRAMQ,DHCreRV,FirstName,Lastname,MdReferantRV,MRN,RAMQ,Resource,ResourceDes,Site,Speciality)
        VALUES
        (:ImportTimestamp,:Result,:ActivityCode,:AppIDComb,:AppDate,:AppTime,:DateExpRAMQ,:DHCreRV,:FirstName,:Lastname,:MdReferantRV,:MRN,:RAMQ,:Resource,:ResourceDes,:Site,:Speciality)"
    );
    $query->execute($requestInfo);



    #send out an email if there was an error
    // if($requestInfo["Result"] !== "Success") {
    //     sendEmail($requestInfo);
    // }
}

#sends an email to the orms admin
function sendEmail(array $requestInfo): void
{
    $recepient = "victor.matassa@muhc.mcgill.ca";
    $subject = "ORMS Appointment Error";
    $message = "ORMS Appointment Error - $requestInfo[Result] for appointment id '$requestInfo[AppIDComb]' at time [$requestInfo[ImportTimestamp]]";
    $headers = [
        "From" => "orms@muhc.mcgill.ca"
    ];

    mail($recepient,$subject,$message,$headers);
}

?>
