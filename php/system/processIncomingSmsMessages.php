<?php declare(strict_types = 1);

require_once __DIR__ ."/SystemLoader.php";

$checkInScriptLocation = Config::getConfigs("path")["BASE_URL"] ."/php/system/checkInPatientAriaMedi.php";
$licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];

#get all the message that we received since the last time we checked
$cURL = curl_init();
curl_setopt_array($cURL,[
    CURLOPT_URL => "https://messaging.cdyne.com/Messaging.svc/ReadIncomingMessages",
    CURLOPT_POST => TRUE,
    CURLOPT_POSTFIELDS => json_encode(["LicenseKey" => $licence,"UnreadMessagesOnly" => TRUE]),
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json","Accept: application/json"]
]);
$messages = json_decode(curl_exec($cURL),TRUE);

#messages have the following structure:
#the values are either string or NULL
/*
Array
    (
        [Attachments]
        [From]
        [IncomingMessageID]
        [OutgoingMessageID]
        [Payload]
        [ReceivedDate] => "/Date(1590840267010-0700)/" //.NET DataContractJsonSerializer format
        [Subject]
        [To]
        [Udh]
    )
*/

#order messages in chronological order (also sorts into the correct order messages split into chunks)
usort($messages,function($x,$y) {
    return $x["IncomingMessageID"] <=> $y["IncomingMessageID"];
});

#long messages are received in chunks so piece together the full message
$messages = ArrayUtilB::groupArrayByKey($messages,"OutgoingMessageID");
$messages = array_map(function($x) {
    $msg = array_reduce($x,function($acc,$y) {
        return $acc . $y["Payload"];
    },"");

    $x = array_merge(...$x);
    $x["Payload"] = $msg;

    #also convert the received utc timezone into the local one
    $utcTime = new DateTime($x["ReceivedDate"],new DateTimeZone("utc"));
    $x["ReceivedDate"] = $utcTime->setTimezone(new DateTimeZone(date_default_timezone_get()))->format("Y-m-d H:i:s");

    return $x;
},$messages);

//filter all non test phone numbers for now
$messages = array_filter($messages,function($x) {
    return ($x["From"] === "15147157890"
		|| $x["From"] === "15144758943");
});

#log all received messages immediately in case the script dies in the middle of processing
foreach($messages as $message)
{
    logData($message["ReceivedDate"],$message["From"],NULL,$message["Payload"],NULL,"SMS received");
}

#process messages
foreach($messages as $message)
{
    #sanitize the phone number
    #the number might have a 1 in front of it; remove it
    $number = $message["From"];
    if(strlen($number) === 11 && $number[0] === "1") $number = substr($number,1);

    #find the patient associated with the phone number
    $patientData = getPatientInfo($number);

    #return nothing if the patient doesn't exist and skip
    if($patientData === NULL)
    {
        logData($message["ReceivedDate"],$message["From"],NULL,$message["Payload"],NULL,"Patient not found");
        continue;
    }

    $patientSer = $patientData["PatientSerNum"];
    $patientMrn = $patientData["PatientId"];
    $language = $patientData["LanguagePreference"];

    #check if the message is equal to the check in keyword ARRIVE

    #sleep first to make sure the previous text message had time to be sent
    sleep(2);

    #if it's not, send a message to the patient instructing them how to use the service
    if(strtoupper($message["Payload"]) !== "ARRIVE")
    {
        if($language === "French") $returnString = "CUSM: Pour vous enregister pour votre rendez-vous, svp repondez \"arrive\". Aucune autre message à ce numéro sera lu.";
        else $returnString = "MUHC: To check-in for an appointment, please reply with the word \"arrive\". No other messages are accepted.";

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDate"],$message["FromPhoneNumber"],$patientSer,$message["Payload"],$returnString,"Success");

        continue;
    }

    #get the patients next appointments for the day
    $appointments = getOrmsAppointments($patientSer);
    usort($appointments,function($x,$y) {
        return $x["time"] <=> $y["time"];
    });

    $appointmentString = array_reduce($appointments,function($acc,$x) use($language) {
        if($language === "French") return $acc . "$x[name] à $x[time]\n";
        else return $acc . "$x[name] at $x[time]\n";
    },"");

    #check the patient into all of his appointments
    $checkInLocation = "CELL PHONE";

    #check in the patient
    if($appointments === [])
    {
        if($language === "French") {
            $returnString = "CUSM: Il n’a pas été possible de vous enregistrer pour votre rendez-vous. Si vous avez un rendez-vous aujourd'hui, veuillez vous diriger à la réception pour vous enregistrer.";
        }
        else {
            $returnString = "MUHC: Problem checking in for your appointment(s). If you have an appointment today, please go to the reception to complete the check-in process.";
        }

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDate"],$message["From"],$patientSer,$message["Payload"],$returnString,"Success");

        continue;
    }

    $checkInRequest = new HttpRequestB($checkInScriptLocation,["CheckinVenue" => $checkInLocation,"PatientId" => $patientMrn]);
    $checkInRequest->executeRequest();
    $checkInResult = $checkInRequest->getRequestHeaders()["http_code"];

    if($checkInResult < 300)
    {
        if($language === "French") {
            $returnString = "CUSM: Vous etes enregistrés pour vos rendez-vous:\n{$appointmentString}Vous recevrez un message quand vous serez appelés pour votre rendez-vous.";
        }
        else {
            $returnString = "MUHC: You have checked in for your appointment(s):\n{$appointmentString}You will receive a message when you are called.";
        }

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDate"],$message["From"],$patientSer,$message["Payload"],$returnString,"Success");
    }
    else
    {
        if($language === "French") {
            $returnString = "CUSM: Il n’a pas été possible de vous enregistrer pour votre rendez-vous. Si vous avez un rendez-vous aujourd'hui, veuillez vous diriger à la réception pour vous enregistrer.";
        }
        else {
            $returnString = "MUHC: Problem checking in for your appointment(s). If you have an appointment today, please go to the reception to complete the check-in process.";
        }

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDate"],$message["From"],$patientSer,$message["Payload"],$returnString,"Error");
    }

}

#functions

function textPatient(string $targetPhoneNumber,string $sourcePhoneNumber,string $returnMessage): void
{
    $licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];
    $url = Config::getConfigs("sms")["SMS_GATEWAY_URL"];

    $cURL = curl_init();
    curl_setopt_array($cURL,[
        CURLOPT_URL => $url,
        CURLOPT_POST => TRUE,
        CURLOPT_POSTFIELDS => json_encode([
            "LicenseKey" => $licence,
            "From" => $sourcePhoneNumber,
            "To" => $targetPhoneNumber,
            "Body" => $returnMessage,
            "Concatenate" => TRUE,
            "UseMMS" => FALSE
        ]),
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json","Accept: application/json"]
    ]);
    $response = json_decode(curl_exec($cURL),TRUE);
}

function getOrmsAppointments(string $pSer): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            MV.AppointmentSerNum AS id,
            MV.ResourceDescription AS name,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time
        FROM
            MediVisitAppointmentList MV
        WHERE
            MV.PatientSerNum = :pSer
            AND MV.ScheduledDate = CURDATE()
            AND MV.Status = 'Open'"
    );
    $query->execute([":pSer" => $pSer]);

    return $query->fetchAll();
}

#classes
function logData(string $timestamp,string $phoneNumber,?string $patientSer,string $message,?string $returnMessage,string $result): void
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        INSERT INTO TEMP_IncomingSmsLog(ReceivedTimestamp,FromPhoneNumber,PatientSerNum,ReceivedMessage,ReturnMessage,Result)
        VALUES(:rec,:num,:pSer,:msg,:rmsg,:res)
    ");
    $query->execute([
        ":rec" => $timestamp,
        ":num" => $phoneNumber,
        ":pSer" => $patientSer,
        ":msg" => $message,
        ":rmsg" => $returnMessage,
        ":res" => $result
    ]);
}

function getPatientInfo(string $phoneNumber): ?array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            Patient.PatientSerNum,
            Patient.PatientId,
            Patient.LanguagePreference
        FROM
            Patient
        WHERE
            Patient.SMSAlertNum = :num
        LIMIT 1
    ");
    $query->execute([":num" => $phoneNumber]);

    return $query->fetchAll()[0] ?? NULL;
}

#Handles making http requests
class HttpRequestB
{
    //private resource $curlObj;
    private $curlObj;

    public function __construct(string $url,array $requestFields = [],string $type = "GET")
    {
        if($type === "GET")
        {
            #if no fields have been provided, then it's assumed that they have already been inserted in the url
            $completedUrl = ($requestFields === []) ? $url : $url."?".http_build_query($requestFields);

            $this->curlObj = curl_init($completedUrl);
            curl_setopt_array($this->curlObj,[
                CURLOPT_RETURNTRANSFER => TRUE
            ]);
        }
        elseif($type === "POST")
        {
            $this->curlObj = curl_init($url);
            curl_setopt_array($this->curlObj,[
                CURLOPT_POSTFIELDS => http_build_query($requestFields),
                CURLOPT_RETURNTRANSFER => TRUE
            ]);
        }
        else throw new \Exception("Request must be of type GET or POST");
    }

    #executes the curl request and returns the output
    #if the execution failed or an empty string is received, returns NULL
    public function executeRequest(): ?string
    {
        return curl_exec($this->curlObj) ?: NULL;
    }

    #get the headers currently stored in the curl object
    #will be empty if called before executeRequest
    public function getRequestHeaders(): array
    {
        return curl_getinfo($this->curlObj);
    }

    #close the curl object handle
    public function closeConnection(): void
    {
        curl_close($this->curlObj);
    }
}

class ArrayUtilB
{
    public static function groupArrayByKey(array $arr,string $key,bool $keepKey = FALSE): array
    {
        $groupedArr = [];
        foreach($arr as $assoc)
        {
            $keyVal = $assoc[$key];
            if(!array_key_exists("$keyVal",$groupedArr)) $groupedArr["$keyVal"] = [];

            if($keepKey === FALSE) unset($assoc[$key]);
            $groupedArr["$keyVal"][] = $assoc;
        }

        ksort($groupedArr);
        return $groupedArr;
    }
}

?>
