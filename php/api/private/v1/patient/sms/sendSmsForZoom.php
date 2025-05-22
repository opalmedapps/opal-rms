<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//====================================================================================
// php code to send an SMS message to a given patient using their cell number for a telemed appointment
//====================================================================================
require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;

$params = Http::getRequestContents();

$patientId  = $params["patientId"] ?? null;
$zoomLink   = $params["zoomLink"] ?? null;
$resName    = $params["resName"] ?? null;

if($patientId === null || $zoomLink === null || $resName === null) {
    Http::generateResponseJsonAndExit(400, error: "Missing parameters!");
}

$patient = PatientInterface::getPatientById($patientId);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Unknown patient");
}

if($patient->phoneNumber === null) {
    Http::generateResponseJsonAndExit(400, error: "Patient has no phone number");
}

//create the message in the patient's preferred language
if($patient->languagePreference === "French") {
    $message = "Teleconsultation CUSM: $zoomLink. Clickez sur le lien pour connecter avec $resName";
}
else {
    $message = "MUHC teleconsultation: $zoomLink. Use this link to connect with $resName.";
}

SmsInterface::sendSms($patient->phoneNumber, $message);

Http::generateResponseJsonAndExit(200);
