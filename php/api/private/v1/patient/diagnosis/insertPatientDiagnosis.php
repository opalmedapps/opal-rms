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
        $is_diagnosis_exists = Fetch::getMasterSourceDiagnosisExists($newDiag->diagnosis->subcode);
        if(!$is_diagnosis_exists){
            Export::insertMasterSourceDiagnosis(
                $newDiag->diagnosis->subcode,
                $newDiag->createdDate,
                $newDiag->diagnosis->subcodeDescription
            );
        }

        // Assign patient the diagnosis
        Export::insertPatientDiagnosis(
            $patient,
            $newDiag->id,
            $newDiag->diagnosis->subcode,
            $newDiag->createdDate,
            $newDiag->diagnosis->subcodeDescription,
            "",
            $newDiag->status
        );
    }else{
        // TODO: Raise error if patient not registered in opal?
    }
    
}
