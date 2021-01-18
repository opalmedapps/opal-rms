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

$mrn = Patient::getPatientById($patientId)->patientId;

//export the diagnosis to external systems
// if($status === "Active") {
//     Opal::updatePatientDiagnosis(
//         $mrn,
//         $updatedDiag->id
//     );
// }
// elseif($status === "Deleted") {
//     Opal::insertPatientDiagnosis(
//         $mrn,
//         $updatedDiag->id,
//         $updatedDiag->diagnosis->subcode,
//         $updatedDiag->createdDate,
//         $updatedDiag->diagnosis->subcodeDescription,
//         ""
//     );
// }

?>
