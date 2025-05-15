<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\Appointment\LocationInterface;
use Orms\DateTime;
use Orms\Patient\PatientInterface;
use Orms\Sms\SmsInterface;
use Orms\System\Logger;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

//get all the message that we received since the last time we checked
//also check up to 5 minutes before the last run to get any messages that may have been missed

//use start of today if there is no last run time
try {
    $lastRun = Logger::getLastSmsProcessorRunTime() ?? (new DateTime())->modify("midnight");
} catch (\Throwable $th) {
    // A temporary failure in DNS lookup to the DB server can cause lots of messages in a short amount of time
    // since this script is executed every x seconds.
    if (str_contains($th->getMessage(), "Temporary failure in name resolution")) {
        // ignore this error
        return;
    }

    throw $th;
}
$currentRun = new DateTime();

Logger::setLastSmsProcessorRunTime($currentRun);

$timeLimit = $currentRun->modify("-5 minutes");

$messages = SmsInterface::getNewReceivedMessages($lastRun->modify("-5 minutes"));

//filter all messages that were sent over 5 minutes ago, just in case
$messages = array_filter($messages, fn($x) => $x->timeReceived >= $timeLimit);

//get default messages
$messageList = SmsInterface::getPossibleSmsMessages();
$checkInLocation = "CELL PHONE";

//process messages
foreach($messages as $message)
{
    //find the patient associated with the phone number
    $patient = PatientInterface::getPatientByPhoneNumber($message->clientNumber);

    //return nothing if the patient doesn't exist and skip
    //also skip if there is no language preference (which should never happen if there is a registered phone number...)
    if($patient === null || $patient->languagePreference === null) {
        continue;
    }

    //check if the message is equal to the check in keyword ARRIVE
    //if it's not, send a message to the patient instructing them how to use the service
    if(!preg_match("/ARRIVE|ARRIVÉ/", mb_strtoupper($message->body)))
    {
        $returnString = SmsInterface::getDefaultUnknownCommandMessage($patient->languagePreference) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        continue;
    }

    //get the patient's next appointments for the day
    $appointments = AppointmentInterface::getOpenAppointments((new DateTime())->modify("midnight"),(new DateTime())->modify("tomorrow"),$patient);

    //filter all appointments where sms is not enabled
    $appointments = array_values(array_filter($appointments,fn($x) => $x["smsActive"] === true && $x["smsType"] !== null));

    //if there are no appointments, the check in was a failure
    if($appointments === [])
    {
        $returnString = SmsInterface::getDefaultFailedCheckInMessage($patient->languagePreference) ?? throw new Exception("Undefined sms string");

        SmsInterface::sendSms($message->clientNumber, $returnString, $message->serviceNumber);
        continue;
    }

    //sort the appointments by speciality and type into a string to insert in the sms message
    //also generate the combined message to send in case the patient has multiple types of appointments
    $appointments = Encoding::utf8_encode_recursive($appointments);
    $appointmentsSorted = ArrayUtil::groupArrayByKeyRecursive($appointments, "specialityGroupId", "smsType");

    $appointmentString = "";
    foreach($appointmentsSorted as $speciality => $v)
    {
        foreach($v as $type => $apps)
        {
            $appointmentString .= $messageList[$speciality][$type]["CHECK_IN"][$patient->languagePreference]["message"] ?? "";

            $appListString = array_reduce($apps, function($acc, $x) use ($patient) {
                $preposition = ($patient->languagePreference === "French") ? "à" : "at";

                $time = $x["scheduledDatetime"]->format("H:i");

                $name = $x["clinicDescription"];
                //hardcoded aria appointment name conversion
                if($x["sourceSystem"] === "Aria") {
                    if(preg_match("/^.EB/",$x["appointmentCode"]) === 1) {
                        $name = "Radiotherapy";
                    }
                    elseif(preg_match("/^(Consult|CONSULT)/",$x["appointmentCode"]) === 1) {
                        $name = "Consult";
                    }
                    elseif(preg_match("/^FOLLOW UP /",$x["appointmentCode"]) === 1) {
                        $name = "Follow Up";
                    }
                    else {
                        $name = $x["appointmentCode"];
                    }
                }

                return $acc ."$name $preposition $time\n";
            }, "");

            $appointmentString = preg_replace("/<app>/", $appListString, $appointmentString);
            $appointmentString .= "\n\n----------------\n\n";
        }
    }
    $appointmentString = rtrim($appointmentString); //remove trailing newlines
    $appointmentString = preg_replace("/\n\n----------------$/", "", $appointmentString) ?? ""; //remove last separator newline

    //check the patient into all of his appointments
    LocationInterface::movePatientToLocation($patient, $checkInLocation, null, "SMS");
    SmsInterface::sendSms($message->clientNumber, $appointmentString, $message->serviceNumber);
}
