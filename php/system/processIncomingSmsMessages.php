<?php declare(strict_types = 1);

require_once __DIR__ ."/SystemLoader.php";

$checkInScriptLocation = Config::getConfigs("path")["BASE_URL"] ."/php/system/checkInPatientAriaMedi.php";
$licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];

#get all the message that we received since the last time we checked
$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL             => "https://messaging.cdyne.com/Messaging.svc/ReadIncomingMessages",
    CURLOPT_POST            => TRUE,
    CURLOPT_POSTFIELDS      => json_encode(["LicenseKey" => $licence, "UnreadMessagesOnly" => TRUE]),
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
]);
$messages = json_decode(curl_exec($curl),TRUE);

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

    #also convert the received utc timestamp into the local one
    #timezone isn't really utc; it actually has an offset
    $timestampWithOffset = preg_replace("/[^0-9 -]/","",$x["ReceivedDate"]);
    $timestamp = (int) (substr($timestampWithOffset,0,-5)/1000);
    $tzOffset = (new DateTime("",new DateTimeZone(substr($timestampWithOffset,-5))))->getOffset();
    $utcTime = (new DateTime("@$timestamp"))->modify("$tzOffset second");

    $x["ReceivedDateStr"] = $utcTime->setTimezone(new DateTimeZone(date_default_timezone_get()))->format("Y-m-d H:i:s");

    return $x;
},$messages);

#log all received messages immediately in case the script dies in the middle of processing
foreach($messages as $message)
{
    logData($message["ReceivedDateStr"],$message["From"],NULL,$message["Payload"],NULL,"SMS received");
}

#get default messages
$messageList = getPossibleSmsMessages();
$checkInLocation = "CELL PHONE";

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
        logData($message["ReceivedDateStr"],$message["From"],NULL,$message["Payload"],NULL,"Patient not found");
        continue;
    }

    $patientSer = $patientData["PatientSerNum"];
    $patientMrn = $patientData["PatientId"];
    $language = $patientData["LanguagePreference"];

    #sleep first to make sure the previous text message had time to be sent
    sleep(2);

    #check if the message is equal to the check in keyword ARRIVE
    #if it's not, send a message to the patient instructing them how to use the service
    if(strtoupper($message["Payload"]) !== "ARRIVE")
    {
        $returnString = $messageList["Any"]["GENERAL"]["UNKNOWN_COMMAND"][$language]["Message"];

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDateStr"],$message["From"],$patientSer,$message["Payload"],$returnString,"Success");

        continue;
    }

    #get the patients next appointments for the day
    $appointments = getAppointmentList($patientSer);

    #if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDateStr"],$message["From"],$patientSer,$message["Payload"],$returnString,"Success");

        continue;
    }

    #sort the appointments by speciality and type into a string to insert in the sms message
    #also generate the combined message to send in case the patient has multiple types of appointments
    $appointmentsSorted = ArrayUtilB::groupArrayByKeyRecursive($appointments,"Speciality","Type");

    $appointmentString = "";
    foreach($appointmentsSorted as $speciality => $v)
    {
        foreach($v as $type => $apps)
        {
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$language]["Message"];

            $appListString = array_reduce($apps,function($acc,$x) use($language) {
                if($language === "French") return $acc . "$x[name] à $x[time]\n";
                else return $acc . "$x[name] at $x[time]\n";
            },"");

            $appointmentString = preg_replace("/<app>/",$appListString,$appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/","",$appointmentString); #remove last newline

    #check the patient into all of his appointments
    $checkInRequest = new HttpRequestB($checkInScriptLocation,["CheckinVenue" => $checkInLocation,"PatientId" => $patientMrn]);
    $checkInRequest->executeRequest();
    $checkInResult = $checkInRequest->getRequestHeaders()["http_code"];

    if($checkInResult < 300)
    {
        textPatient($message["From"],$message["To"],$appointmentString);
        logData($message["ReceivedDateStr"],$message["From"],$patientSer,$message["Payload"],$appointmentString,"Success");
    }
    else
    {
        $returnString = $messageList["Any"]["GENERAL"]["FAILED_CHECK_IN"][$language]["Message"];

        textPatient($message["From"],$message["To"],$returnString);
        logData($message["ReceivedDateStr"],$message["From"],$patientSer,$message["Payload"],$returnString,"Error");
    }
}

#functions

/* messages are classified by speciality, type, and event:
    speciality is the speciality group the message is used in
    type is subcategory of the speciality group and is used to link the appointment code to a message
    event indicates when the message should be sent out (during check in, as a reminder, etc)
*/
function getPossibleSmsMessages(): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            Speciality
            ,Type
            ,Event
            ,Language
            ,Message
        FROM
            SmsMessage
        ORDER BY
            Speciality,Type,Event,Language
    ");
    $query->execute();

    $messages = $query->fetchAll();
    $messages = ArrayUtilB::groupArrayByKeyRecursive($messages,"Speciality","Type","Event","Language");
    $messages = ArrayUtilB::convertSingleElementArraysRecursive($messages);

    return utf8_encode_recursive($messages);
}

function textPatient(string $targetPhoneNumber,string $sourcePhoneNumber,string $returnMessage): void
{
    #don't send anything is the message is empty
    if($returnMessage === "") return;

    $licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];
    $url = Config::getConfigs("sms")["SMS_GATEWAY_URL"];

    $fields = [
        "Body" => $returnMessage,
        "LicenseKey" => $licence,
        "From" => $sourcePhoneNumber,
        "To" => [$targetPhoneNumber],
        "Concatenate" => TRUE,
        "UseMMS" => FALSE,
        "IsUnicode" => TRUE
    ];

    $curl = curl_init();
    curl_setopt_array($curl,[
        CURLOPT_URL             => $url,
        CURLOPT_POST            => TRUE,
        CURLOPT_POSTFIELDS      => json_encode($fields),
        CURLOPT_RETURNTRANSFER  => TRUE,
        CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
    ]);
    curl_exec($curl);
}

function getAppointmentList(string $pSer): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            MV.AppointmentSerNum AS id,
            CASE
                WHEN MV.AppointSys = 'Aria' THEN
                    CASE
                        WHEN MV.AppointmentCode LIKE '.EB%' THEN 'Radiotherapy'
                        WHEN (MV.AppointmentCode LIKE 'Consult%'
                            OR MV.AppointmentCode LIKE 'CONSULT%') THEN 'Consult'
                        WHEN MV.AppointmentCode LIKE 'FOLLOW UP %' THEN 'Follow Up'
                        ELSE MV.AppointmentCode
                    END
                ELSE MV.ResourceDescription
            END AS name,
            MV.ScheduledDate AS date,
            TIME_FORMAT(MV.ScheduledTime,'%H:%i') AS time,
            SmsAppointment.Speciality,
            SmsAppointment.Type
        FROM
            MediVisitAppointmentList MV
            INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN SmsAppointment ON SmsAppointment.AppointmentCode = MV.AppointmentCode
                AND SmsAppointment.Speciality = ClinicResources.Speciality
        WHERE
            MV.PatientSerNum = :pSer
            AND MV.ScheduledDate = CURDATE()
            AND MV.Status = 'Open'
        ORDER BY MV.ScheduledTime
    ");
    $query->execute([":pSer" => $pSer]);

    return utf8_encode_recursive($query->fetchAll());
}

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

#classes

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

    #recursive version of groupArrayByKey that repeats the grouping process for each input key
    public static function groupArrayByKeyRecursive(array $arr,string ...$keys): array
    {
        $key = array_shift($keys);
        if($keys === NULL) return $arr;

        $groupedArr = self::groupArrayByKey($arr,"$key");

        if($keys !== [])
        {
            foreach($groupedArr as &$subArr) {
                $subArr = self::groupArrayByKeyRecursive($subArr,...$keys);
            }
        }

        return $groupedArr;
    }

    public static function convertSingleElementArraysRecursive($arr)
    {
        if(gettype($arr) === "array")
        {
            foreach($arr as &$val) $val = self::convertSingleElementArraysRecursive($val);

            if(self::checkIfArrayIsAssoc($arr) === FALSE && count($arr) === 1) {
                $arr = $arr[0];
            }
        }

        return $arr;
    }

    public static function checkIfArrayIsAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0,count($arr)-1);
    }

}

?>
