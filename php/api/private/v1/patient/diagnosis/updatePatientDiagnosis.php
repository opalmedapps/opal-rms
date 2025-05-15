<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\External\LegacyOpalAdmin\Export;
use Orms\External\LegacyOpalAdmin\Fetch;
use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$patientId           = (int) $params["patientId"];
$patientDiagnosisId  = (int) $params["patientDiagnosisId"];
$diagnosisId         = (int) $params["diagnosisId"];
$diagnosisDate       = new DateTime($params["diagnosisDate"] ?? "");
$status              = $params["status"];
$user                = $params["user"];

$updatedDiag = DiagnosisInterface::updatePatientDiagnosis($patientDiagnosisId, $diagnosisId, $diagnosisDate, $status, $user);

Http::generateResponseJsonAndContinue(200);

//export the diagnosis to external systems
$patient = PatientInterface::getPatientById($patientId);

if($patient !== null) {
    $is_opal_patient = Fetch::isOpalPatient($patient);
    
    if($is_opal_patient){

        // Ensure the code to be assigned exists in the Opal MasterSource list
        $diagnosis_exists = Fetch::getMasterSourceDiagnosisExists($updatedDiag->diagnosis->subcode);
        if(!$diagnosis_exists){
            Export::insertMasterSourceDiagnosis(
                $updatedDiag->diagnosis->subcode,
                $updatedDiag->createdDate,
                $updatedDiag->diagnosis->subcodeDescription,
                ""
            );
        }
        if (trim(strtolower($status)) === 'deleted') {
            // Separate endpoint for diagnosis deletions
            Export::deletePatientDiagnosis(
                $patient,
                $updatedDiag->id,
                $updatedDiag->diagnosis->subcode,
                $updatedDiag->createdDate,
                $updatedDiag->diagnosis->subcodeDescription,
                "",
                $updatedDiag->status
            );
        }else{
            // Assign patient the diagnosis
            Export::insertPatientDiagnosis(
                $patient,
                $updatedDiag->id,
                $updatedDiag->diagnosis->subcode,
                $updatedDiag->createdDate,
                $updatedDiag->diagnosis->subcodeDescription,
                "",
                $updatedDiag->status
            ); 
        }

    }else{
        // TODO: Raise error if patient not registered in opal?
    }
    
}

