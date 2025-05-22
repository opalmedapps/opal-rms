<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//api that is called when a patient has answered a questionnaire in Opal, letting ORMS know to to regenerate the list of answered questionnaires for the day

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Config;
use Orms\DataAccess\ReportAccess;
use Orms\External\LegacyOpalAdmin\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

//get the list of Opal patients who have an appointment today
$appointments = ReportAccess::getCurrentDaysAppointments();

$patients = array_map(function($x) {
    $patient = null;
    if($x["OpalPatient"] === 1) {
        $patient = PatientInterface::getPatientById($x["PatientId"]);

        if($patient === null) {
            error_log((string) new Exception("Unknown patient id $x[PatientId]"));
        }
    }

    return $patient;
},$appointments);

$opalPatients = array_values(array_filter(array_unique($patients,SORT_REGULAR)));


//fetch questionnaire data for opal patients
try {
    $lastCompletedQuestionnaires = Fetch::getLastCompletedQuestionnaireForPatients($opalPatients);
}
catch(Exception $e) {
    $lastCompletedQuestionnaires = [];
    error_log((string) $e);
}

//save the results in a json file for use elsewhere
$path = Config::getApplicationSettings()->environment->completedQuestionnairePath;

$checkinlist = fopen($path, "w");
if($checkinlist === false) {
    throw new Exception("Unable to open checkinlist file!");
}

fwrite($checkinlist, json_encode($lastCompletedQuestionnaires) ?: "[]");
