<?php declare(strict_types = 1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\DateTime;
use Orms\Patient;
use Orms\Opal;

$patientId = (int) $_GET["patientId"];
$patientDiagnosisId = (int) $_GET["patientDiagnosisId"];
$diagnosisId = (int) $_GET["diagnosisId"];
$diagnosisDate = new DateTime($_GET["diagnosisDate"] ?? "");
$status = $_GET["status"];

$updatedDiag = DiagnosisInterface::updatePatientDiagnosis($patientDiagnosisId,$diagnosisId,$diagnosisDate,$status);

//export the diagnosis to external systems
$mrn = Patient::getPatientById($patientId)->patientId;

if($mrn !== NULL)
{
    if($status === "Active") {
        Opal::insertPatientDiagnosis(
            $mrn,
            $updatedDiag->diagnosis->id, // $newDiag->id,
            $updatedDiag->diagnosis->subcode,
            $updatedDiag->createdDate,
            $updatedDiag->diagnosis->subcodeDescription,
            ""
        );
    }
    elseif($status === "Deleted") {
        Opal::deletePatientDiagnosis(
            $mrn,
            $updatedDiag->diagnosis->id
        );
    }
}



?>
