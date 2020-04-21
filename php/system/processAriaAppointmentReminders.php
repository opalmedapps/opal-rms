<?php declare(strict_types = 1);

require_once __DIR__ ."/SystemLoader.php";


#get the list of aria appointments to process
#group appointments by patient
$appointments = getAriaAppointments();
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

function getAriaAppointments(): array
{
    $dbh = Config::getDatabaseConnection("ARIA");
    $query = $dbh->prepare("
        SELECT
            Patient.PatientId as mrn,
            ScheduledActivity.ScheduledActivitySer AS appSer,
            CAST(ScheduledActivity.ScheduledStartTime AS DATE) AS date,
            CAST(CAST(ScheduledActivity.ScheduledStartTime AS TIME) AS VARCHAR(5)) AS time,
            vv_Activity.Expression1 as fullname,   
            CASE
                WHEN LTRIM(RTRIM(vv_Activity.Expression1)) LIKE '.EB%' THEN 'Radiotherapy'
                WHEN (LTRIM(RTRIM(vv_Activity.Expression1)) LIKE 'Consult%'
                    OR LTRIM(RTRIM(vv_Activity.Expression1)) LIKE 'CONSULT%') THEN 'Consult'
                WHEN LTRIM(RTRIM(vv_Activity.Expression1)) LIKE 'FOLLOW UP %' THEN 'Follow Up'
                ELSE LTRIM(RTRIM(vv_Activity.Expression1))
            END AS name
        FROM
            Patient
            INNER JOIN ScheduledActivity ON ScheduledActivity.PatientSer = Patient.PatientSer
                AND CAST(ScheduledActivity.ScheduledStartTime AS DATE) = DATEADD(DAY,1,CAST(GETDATE() AS DATE))
                AND ScheduledActivity.ObjectStatus = 'Active'
                AND ScheduledActivity.ScheduledActivityCode = 'Open'
            INNER JOIN ActivityInstance ON ActivityInstance.ActivityInstanceSer = ScheduledActivity.ActivityInstanceSer
            INNER JOIN Activity ON Activity.ActivitySer = ActivityInstance.ActivitySer
            INNER JOIN vv_Activity ON vv_Activity.LookupValue = Activity.ActivityCode
                AND vv_Activity.SubSelector = ActivityInstance.DepartmentSer
                AND vv_Activity.Expression1 NOT LIKE 'NUTRITION%'
		AND vv_Activity.Expression1 NOT LIKE '%On Hold%'
		AND vv_Activity.Expression1 NOT LIKE '%Cancelled%'
		AND vv_Activity.Expression1 NOT LIKE '%Portal Only%'
		AND vv_Activity.Expression1 NOT LIKE '%Pt Booked%'
		AND vv_Activity.Expression1 NOT LIKE '%Waiting%'
                AND (
                    vv_Activity.Expression1 LIKE '.EB%'
                    OR vv_Activity.Expression1 LIKE '.BX%'
                    OR vv_Activity.Expression1 LIKE 'CT Sim%'
                    -- OR vv_Activity.Expression1 LIKE 'Consult%'
                    -- OR vv_Activity.Expression1 LIKE 'CONSULT%'
                    -- OR vv_Activity.Expression1 LIKE '%FOLLOW UP %'
                    -- OR vv_Activity.Expression1 LIKE 'INTRA TREAT%'
                    -- OR vv_Activity.Expression1 = 'Transfusion'
                    -- OR vv_Activity.Expression1 = 'Injection'
                    -- OR vv_Activity.Expression1 = 'Hydration'
                    -- OR vv_Activity.Expression1 = 'Nursing Consult'
                )
        ORDER BY
            ScheduledActivity.ScheduledStartTime
    ");
    $query->execute();

    #get the phone number of the patient
    $appointments = array_map(function($x) {
        $phoneInfo = getPatientPhoneNumber($x["mrn"]);
        $x["phoneNumber"] = $phoneInfo["SMSAlertNum"] ?? NULL;
        $x["language"] = $phoneInfo["LanguagePreference"] ?? NULL;

        return $x;
    },$query->fetchAll());

    #filter the appointment if no phone number
    $appointments = array_filter($appointments,function($x) {
        return ($x["phoneNumber"] !== NULL);
    });

    #filter if a reminder was already sent for this appointment
    $appointments = array_filter($appointments,function($x) {
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

