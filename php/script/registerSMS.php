<?php
//====================================================================================
// php code to insert patients' cell phone numbers and languqge preferences
// into ORMS
//====================================================================================
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

#print header
header('Content-Type:text/html; charset=UTF-8');

# SMS Stuff
$smsSettings = Config::getConfigs("sms");

$SMS_licencekey = $smsSettings["SMS_LICENCE_KEY"];
$SMS_gatewayURL = $smsSettings["SMS_GATEWAY_URL"];

// Extract the webpage parameters
$PatientId          = $_GET["PatientId"] ?? '';
$Ramq               = $_GET["Ramq"] ?? '';
$SMSAlertNum        = $_GET["SMSAlertNum"] ?? '';
$LanguagePreference = $_GET["LanguagePreference"] ?? '';
$Speciality         = $_GET["Speciality"];

if( empty($PatientId) || empty($Ramq) || empty($SMSAlertNum) || empty($LanguagePreference) )
{
  exit("All input parameters are required - please use back button and fill out form completely");
}

//====================================================================================
// 2019-03-22 YM : Force the RAMQ to upper case
//====================================================================================
$Ramq = strtoupper($Ramq);

//====================================================================================
// Database - ORMS
//====================================================================================
// Create MySQL DB connection
$dbh = Config::getDatabaseConnection("ORMS");

// Check connection
if (!$dbh) {
    die("<br>Connection failed");
}

//====================================================================================
// If Patient exists in ORMS, update the preferences
//====================================================================================
// first check that the patient exists in ORMS
$sqlPatient= "
	SELECT Patient.LastName
	FROM Patient
	WHERE  Patient.PatientId =  '$PatientId'
";

/* Process results */
$result = $dbh->query($sqlPatient);

$LastName;
if ($result->rowCount() > 0) {
    // output data of each row
    $row = $result->fetch();

    $LastName = $row["LastName"];
}

$PatientInORMS;
if(!empty($LastName) )
{
  $PatientInORMS = TRUE;
  echo "Patient already exists in ORMS - will update...<br>";
}
else
{
  $PatientInORMS = FALSE;
}

//====================================================================================
// Exit now if the patient does not exist in either ORM
//====================================================================================
if(!$PatientInORMS)
{
  exit("This patient does not exist in either ORMS. Patient must have an appointment in order to be set up for SMS<br>");
}

//====================================================================================
// Write to the ORMS db - either an update if patient already exists in ORMS
//====================================================================================
if($PatientInORMS)
{
  $sqlSMS = "
	UPDATE Patient
    SET
        Patient.SMSAlertNum='$SMSAlertNum',
        Patient.SMSSignupDate = NOW(),
		Patient.SMSLastUpdated = NOW(),
		Patient.LanguagePreference='$LanguagePreference'
	WHERE Patient.PatientId = '$PatientId' AND Patient.SSN = '$Ramq'
  ";
}

echo "SMS: $sqlSMS<br>";


if ($dbh->query($sqlSMS)) {
    echo "Record updated successfully<br>";
} else {
    echo "Error updating record: " . print_r($dbh->errorInfo());
    exit("This record could not be updated. Please mark the patient's form and contact opal@muhc.mcgill.ca.<br>");
}

echo "<br>";

//====================================================================================
// Confirmation Message Creation
//====================================================================================
$messageList = getPossibleSmsMessages();

$message = $messageList[$Speciality]["GENERAL"]["REGISTRATION"][$LanguagePreference]["Message"] ?? NULL;

if($message === NULL) {
    exit("Registration message not defined");
}

//====================================================================================
// Sending
//====================================================================================
$fields = [
    "Body" => $message,
    "LicenseKey" => $SMS_licencekey,
    "To" => [$SMSAlertNum],
    "Concatenate" => TRUE,
    "UseMMS" => FALSE,
    "IsUnicode" => TRUE
];

$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL             => $SMS_gatewayURL,
    CURLOPT_POST            => TRUE,
    CURLOPT_POSTFIELDS      => json_encode($fields),
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_HTTPHEADER      => ["Content-Type: application/json","Accept: application/json"]
]);
$response = curl_exec($curl);
// $headers = curl_getinfo($curl);

echo "Message should have been sent...";

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
