<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DateTime;
use Orms\Diagnosis\DiagnosisInterface;
use Orms\External\OIE\Export;
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
    Export::exportPatientDiagnosis(
        $patient,
        $newDiag->id,
        $newDiag->diagnosis->subcode,
        $newDiag->createdDate,
        $newDiag->diagnosis->subcodeDescription,
        "",
        $newDiag->status
    );
}
