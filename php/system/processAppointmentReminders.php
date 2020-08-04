<?php declare(strict_types = 1);

require_once __DIR__ ."/SystemLoader.php";

#get the list of aria appointments to process
#group appointments by patient
$appointments = getAppointments();
$patients = ArrayUtilB::groupArrayByKeyRecursiveKeepKeys($appointments,"mrn");

$messageList = getPossibleSmsMessages();

#merge appointment entries for the same patient
#combine all appointments a patient has into a string with the appropriate messages(s)
$patients = array_map(function($x) use ($messageList) {

    $appts = ArrayUtilB::groupArrayByKeyRecursiveKeepKeys($x,"speciality","type");

    $appointmentString = "";
    foreach($appts as $speciality => $v)
    {
        foreach($v as $type => $arr)
        {
            $appointmentString .= $messageList[$speciality][$type]["REMINDER"][$arr[0]["language"]]["Message"];

            $appListString = array_reduce($arr,function($acc,$y) {
                if($y["language"] === "French") return $acc . "$y[name] le $y[date] à $y[time]\n";
                else return $acc . "$y[name] on $y[date] at $y[time]\n";
            },"");

            $appointmentString = preg_replace("/<app>/",$appListString,$appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = preg_replace("/\n\n----------------\n\n$/","",$appointmentString); #remove last newline

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

#send sms to patient
foreach($patients as $pat)
{
    textPatient($pat["phoneNumber"],$pat["appString"]);
    logData($pat["mrn"],$pat["phoneNumber"],$pat["appString"],$pat["appSer"],$pat["appName"]);
    #sleep first to make sure the previous text message had time to be sent
    sleep(2);
}


#functions

function textPatient(string $phoneNumber,string $returnMessage): void
{
    #don't send anything is the message is empty
    if($returnMessage === "") return;

    $licence = Config::getConfigs("sms")["SMS_LICENCE_KEY"];
    $url = Config::getConfigs("sms")["SMS_GATEWAY_URL"];

    $fields = [
        "Body" => $returnMessage,
        "LicenseKey" => $licence,
        "To" => [$phoneNumber],
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
    $messages = ArrayUtilB::groupArrayByKeyRecursiveKeepKeys($messages,"Speciality","Type","Event","Language");
    $messages = ArrayUtilB::convertSingleElementArraysRecursive($messages);

    return utf8_encode_recursive($messages);
}

function getAppointments(): array
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT
            Patient.PatientId AS mrn,
            Patient.SMSAlertNum AS phoneNumber,
            Patient.LanguagePreference AS language,
            MV.AppointmentSerNum AS appSer,
            MV.ScheduledDate AS date,
            MV.ScheduledTime AS time,
            MV.ResourceDescription AS fullname,
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
            ClinicResources.Speciality AS speciality,
            SmsAppointment.Type as type
        FROM
            MediVisitAppointmentList MV
            INNER JOIN Patient ON Patient.PatientSerNum = MV.PatientSerNum
                AND Patient.SMSAlertNum != ''
                AND Patient.SMSAlertNum IS NOT NULL
            INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            INNER JOIN SmsAppointment ON SmsAppointment.AppointmentCode = MV.AppointmentCode
                AND SmsAppointment.Speciality = ClinicResources.Speciality
        WHERE
        MV.Status = 'Open'
            AND MV.ScheduledDate = CURDATE() + INTERVAL 1 DAY
        ORDER BY
            Patient.PatientId,
            MV.ScheduledTime
    ");
    $query->execute();

    #filter if a reminder was already sent for this appointment
    $appointments = array_filter($query->fetchAll(),function($x) {
	    return checkIfReminderAlreadySent($x["appSer"]);
    });

    return utf8_encode_recursive($appointments);
}

#checks to see if a reminder was already sent for a specific appointment
function checkIfReminderAlreadySent(string $appSer): bool
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        SELECT SmsReminderLogSer
        FROM TEMP_SmsReminderLog
        WHERE AppointmentSer LIKE :appSer
        LIMIT 1
    ");
    $query->execute([":appSer" => "%$appSer%"]);

     return ($query->fetchAll()[0]["SmsReminderLogSer"] ?? FALSE) ? FALSE : TRUE;
}

function logData(string $mrn,string $phoneNumber,string $message,string $appSer,$appName): void
{
    $dbh = Config::getDatabaseConnection("ORMS");
    $query = $dbh->prepare("
        INSERT INTO TEMP_SmsReminderLog(Mrn,PhoneNumber,MessageSent,AppointmentSer,AppointmentName)
        VALUES(?,?,?,?,?)
    ");
    $query->execute([$mrn,$phoneNumber,$message,$appSer,$appName]);
}

#classes

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
