<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$params = Http::getRequestContents();

$patientId          = $params["patientId"] ?? null;
$phoneNumber        = $params["phoneNumber"] ?? null;
$languagePreference = $params["languagePreference"] ?? null;
$specialityGroupId  = $params["specialityGroupId"] ?? null;

if($patientId === null || $languagePreference === null || $specialityGroupId === null) {
    Http::generateResponseJsonAndExit(400, error: "Input parameters are missing");
}

//find the patient and update their phone number (or remove it)
$patient = PatientInterface::getPatientById((int) $patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

$patient = PatientInterface::updatePatientInformation($patient, phoneNumber: $phoneNumber, languagePreference: $languagePreference);

//send the patient a registration success message if the patient has a phone number
if($patient->phoneNumber !== null)
{
    $messageList = SmsInterface::getPossibleSmsMessages();
    $message = $messageList[$specialityGroupId]["GENERAL"]["REGISTRATION"][$languagePreference]["message"] ?? null;

    if($message === null) {
        Http::generateResponseJsonAndExit(400, error: "Registration message not defined");
    }

    //print a message and close the connection so that the client does not wait
    Http::generateResponseJsonAndContinue(200);
    SmsInterface::sendSms($patient->phoneNumber, $message);
}
else {
    Http::generateResponseJsonAndExit(200);
}
