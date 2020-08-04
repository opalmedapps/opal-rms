<?php
//====================================================================================
// php code to insert patients' cell phone numbers and language preferences
// into ORMS
//====================================================================================
require("loadConfigs.php");

#extract the webpage parameters
$patientIdRVH       = $_GET["patientIdRVH"] ?? NULL;
$patientIdMGH       = $_GET["patientIdMGH"] ?? NULL;
$smsAlertNum        = $_GET["phoneNumber"] ?? NULL;
$languagePreference = $_GET["language"] ?? NULL;

#find the patient in ORMS
$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);
$queryOrms = $dbh->prepare("
    SELECT
        Patient.PatientSerNum
    FROM
        Patient
    WHERE
        Patient.PatientId = :patIdRVH
        AND Patient.PatientId_MGH = :patIdMGH
");
$queryOrms->execute([
    ":patIdRVH" => $patientIdRVH,
    ":patIdMGH" => $patientIdMGH
]);

$patSer = $queryOrms->fetchAll()[0]["PatientSerNum"] ?? NULL;

if($patSer === NULL) exit("Patient not found");

#if the phone number provided was empty, then unsuscribe the patient to the service instead
if($smsAlertNum === "")
{
    #set the patient phone number
    $querySMS = $dbh->prepare("
        UPDATE Patient
        SET
            SMSAlertNum = NULL,
            SMSSignupDate = NULL,
            LanguagePreference = NULL
        WHERE
            PatientSerNum = :pSer"
    );
    $querySMS->execute([
        ":pSer"     => $patSer
    ]);

    exit("Record updated successfully<br>");
}

#set the patient phone number
$querySMS = $dbh->prepare("
    UPDATE Patient
    SET
        SMSAlertNum = :phoneNum,
        SMSSignupDate = NOW(),
        LanguagePreference = :langPref
    WHERE
        PatientSerNum = :pSer"
);
$querySMS->execute([
    ":phoneNum" => $smsAlertNum,
    ":langPref" => $languagePreference,
    ":pSer"     => $patSer
]);

echo "Record updated successfully<br>";

#change the sms message depending on the language preference and clinic
$messageList = getPossibleSmsMessages();
$message = $messageList["Oncology"]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"];

#send sms
$SMS_licencekey = SMS_licencekey;
$SMS_gatewayURL = SMS_gatewayURL;

$fields = [
    "Body" => $message,
    "LicenseKey" => $SMS_licencekey,
    "To" => [$smsAlertNum],
    "Concatenate" => TRUE,
    "UseMMS" => FALSE
];

$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL             => $SMS_gatewayURL,
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => json_encode($fields),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
]);
curl_exec($curl);



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
