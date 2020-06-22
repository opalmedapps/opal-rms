<?php declare(strict_types = 1);

require_once __DIR__ ."/SystemLoader.php";


#get the list of aria appointments to process
#group appointments by patient
$appointments = getAppointments();
$patients = ArrayUtilB::groupArrayByKeyRecursiveKeepKeys($appointments,"mrn");

#merge appointment entries for the same patient
#combine all appointments a patient has into a string
$patients = array_map(function($x) {
    $appointmentString = array_reduce($x,function($acc,$y) {
        if($y["language"] === "French") return $acc . "$y[name] le $y[date] à $y[time]\n";
        else return $acc . "$y[name] on $y[date] at $y[time]\n";
    },"");

    $appointmentSerString = array_reduce($x,function($acc,$y) {
	return $acc . "$y[appSer],";
    },"");

    $appointmentNameString = array_reduce($x,function($acc,$y) {
	return $acc . "$y[fullname],";
    },"");

    if(ArrayUtilB::checkIfArrayIsAssoc($x) === FALSE) $x = array_merge(...$x);

    unset($x["date"]);
    unset($x["time"]);
    unset($x["name"]);
    $x["appString"] = $appointmentString;
    $x["appSer"] = $appointmentSerString;
    $x["appName"] = $appointmentNameString;

    return $x;
},$patients);

//filter all non test patients for now
//$patients = array_filter($patients,function($x) {
//    return ($x["mrn"] === "9999996" || $x["mrn"] === "9999997");
//});

#send sms to patient
foreach($patients as $pat)
{
    if($pat["language"] === "French") {
        $message = "CUSM: Rappel pour vos rendez-vous:\n{$pat["appString"]}Si vous venez en voiture pour radiothérapie seulement, notez qu'il est possible de s'enregistrer dans le terrain de stationnement. Plus de details à: https://tinyurl.com/cusmradio";
    }
    else {
        $message = "MUHC: Reminder for your appointment(s):\n{$pat["appString"]}If coming by car for radiotherapy only, please note that check-in from the parking lot is possible. More details at: https://tinyurl.com/muhcradonc";
    }

    textPatient($pat["phoneNumber"],$message);
    logData($pat["mrn"],$pat["phoneNumber"],$message,$pat["appSer"],$pat["appName"]);
    #sleep first to make sure the previous text message had time to be sent
    sleep(2);
}


#functions

function textPatient(string $phoneNumber,string $returnMessage): void
{
    $licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];
    $url = Config::getConfigs("sms")["SMS_GATEWAY_URL"];

    $fields = [
        "PhoneNumber" => $phoneNumber,
        "Message"     => $returnMessage,
        "LicenseKey"  => $licence
    ];
    $response = (new HttpRequestB($url,$fields))->executeRequest();
}

function getPatientPhoneNumber(string $mrn): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            Patient.SMSAlertNum,
            Patient.LanguagePreference
        FROM
            Patient
        WHERE
            Patient.PatientId = :mrn
            AND Patient.SMSAlertNum != ''
            AND Patient.SMSAlertNum IS NOT NULL
        LIMIT 1
    ");
    $query->execute([":mrn" => $mrn]);

    return $query->fetchAll()[0] ?? [];
}

function getAppointments(): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            Patient.PatientId AS mrn,
            Patient.SMSAlertNum AS phoneNumber,
            Patient.LanguagePreference AS language,
            MediVisitAppointmentList.AppointmentSerNum AS appSer,
            MediVisitAppointmentList.ScheduledDate AS date,
            MediVisitAppointmentList.ScheduledTime AS time,
            MediVisitAppointmentList.ResourceDescription AS fullname,
            MediVisitAppointmentList.ResourceDescription AS name,
            ClinicResource.Speciality AS speciality
        FROM
            MediVisitAppointmentList
            INNER JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
                AND Patient.SMSAlertNum != ''
                AND Patient.SMSAlertNum IS NOT NULL
            INNER JOIN ClinicResources ON ClinicResources.ResourceName = MediVisitAppointmentList.ResourceDescription
        WHERE
            MediVisitAppointmentList.Status = 'Open'
            AND MediVisitAppointmentList.ScheduledDate = CURDATE() + INTERVAL 1 DAY
        ORDER BY
            MediVisitAppointmentList.ScheduledTime
    ");
    $query->execute();

    #filter if a reminder was already sent for this appointment
    $appointments = array_filter($query->fetchAll(),function($x) {
	    return checkIfReminderAlreadySent($x["appSer"]);
    });

    return $appointments;
}

#checks to see if a reminder was already sent for a specific appointment
function checkIfReminderAlreadySent(string $appSer): bool
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT AriaReminderLogSer
        FROM TEMP_AriaReminderLog
        WHERE AppointmentSer LIKE :appSer
        LIMIT 1
    ");
    $query->execute([":appSer" => "%$appSer%"]);

     return ($query->fetchAll()[0]["AriaReminderLogSer"] ?? FALSE) ? FALSE : TRUE;
}

function logData(string $mrn,string $phoneNumber,string $message,string $appSer,$appName): void
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        INSERT INTO TEMP_AriaReminderLog(Mrn,PhoneNumber,MessageSent,AppointmentSer,AppointmentName)
        VALUES(?,?,?,?,?)
    ");
    $query->execute([$mrn,$phoneNumber,$message,$appSer,$appName]);
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

    #version of groupArrayByKeyRecursive that keeps the original keys intact
    public static function groupArrayByKeyRecursiveKeepKeys(array $arr,string ...$keys): array
    {
        $key = array_shift($keys);
        if($keys === NULL) return $arr;

        $groupedArr = self::groupArrayByKey($arr,"$key",TRUE);

        if($keys !== [])
        {
            foreach($groupedArr as &$subArr) {
                $subArr = self::groupArrayByKeyRecursiveKeepKeys($subArr,...$keys);
            }
        }

        return $groupedArr;
    }


    public static function checkIfArrayIsAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0,count($arr)-1);
    }
}

?>
