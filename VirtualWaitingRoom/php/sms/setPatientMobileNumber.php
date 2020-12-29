<?php
//====================================================================================
// php code to insert patients' cell phone numbers and language preferences
// into ORMS
//====================================================================================
require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;
use Orms\SmsInterface;

#extract the webpage parameters
$patientId          = $_GET["patientId"] ?? NULL;
$smsAlertNum        = $_GET["phoneNumber"] ?? NULL;
$languagePreference = $_GET["language"] ?? NULL;
$speciality         = $_GET["speciality"] ?? NULL;

$dbh = Config::getDatabaseConnection("ORMS");

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
        ":pSer" => $patientId
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
    ":pSer"     => $patientId
]);

#print a message and close the connection so that the client does not wait
ob_start();
echo "Record updated successfully<br>";
header('Connection: close');
header('Content-Length: '.ob_get_length());
ob_end_flush();
ob_flush();
flush();

#change the sms message depending on the language preference and clinic
$messageList = SmsInterface::getPossibleSmsMessages();
$message = $messageList[$speciality]["GENERAL"]["REGISTRATION"][$languagePreference]["Message"];

#send sms
SmsInterface::sendSms($smsAlertNum,$message);

?>
