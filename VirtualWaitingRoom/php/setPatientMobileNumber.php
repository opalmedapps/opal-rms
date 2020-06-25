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
if($languagePreference === "French")
{
    $message = "CUSM: l'inscription pour les notifications est confirmée. Pour vous désabonner, veuillez informer la réception. Pour enregistrer pour un rendez-vous, répondez à ce numéro avec \"arrive\".";
}
else
{
    $message = "MUHC: You are registered for SMS notifications. To unsubscribe, please inform the reception. To check-in for an appointment, reply to this number with \"arrive\".";
}

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

?>
