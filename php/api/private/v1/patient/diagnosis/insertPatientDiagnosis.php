<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\External\LegacyOpalAdmin\Export;
use Orms\External\LegacyOpalAdmin\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId      = (int) $params["patientId"];
$mrn            = $params["mrn"];
$site           = $params["site"];
$diagnosisId    = (int) $params["diagnosisId"];
$diagnosisDate  = new DateTime($params["diagnosisDate"]);
$user           = $params["user"];

$newDiag = DiagnosisInterface::insertPatientDiagnosis($patientId, $mrn, $site, $diagnosisId, $diagnosisDate, $user);

Http::generateResponseJsonAndContinue(200);

//export the diagnosis to external systems
$patient = PatientInterface::getPatientById($patientId);

if($patient !== null) {
    $is_opal_patient = Fetch::isOpalPatient($patient);
    
    if($is_opal_patient){

        // Ensure the code to be assigned exists in the Opal MasterSource list
        $diagnosis_exists = Fetch::getMasterSourceDiagnosisExists($newDiag->diagnosis->subcode);
        if(!$diagnosis_exists){
            try{
                $response = Export::insertMasterSourceDiagnosis(
                    $newDiag->diagnosis->subcode,
                    $newDiag->createdDate,
                    $newDiag->diagnosis->subcodeDescription,
                    ""
                );
            }catch(\Exception $e) {
                Http::generateResponseJsonAndExit(
                    httpCode: $response->getStatusCode(),
                    error: $e->getMessage(),
                );
            }
        }

        // Assign patient the diagnosis
        try{
            $response = Export::insertPatientDiagnosis(
                $patient,
                $newDiag->id,
                $newDiag->diagnosis->subcode,
                $newDiag->createdDate,
                $newDiag->diagnosis->subcodeDescription,
                "",
                $newDiag->status
            );
        }catch(\Exception $e) {
            Http::generateResponseJsonAndExit(
                httpCode: $response->getStatusCode(),
                error: $e->getMessage(),
            );
        }
    }
}
