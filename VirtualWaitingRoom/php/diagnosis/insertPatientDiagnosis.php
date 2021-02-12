<?php declare(strict_types = 1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DiagnosisInterface;
use Orms\DateTime;
use Orms\Patient;
use Orms\Opal;

$patientId = (int) $_GET["patientId"];
$diagnosisId = (int) $_GET["diagnosisId"];
$diagnosisDate = new DateTime($_GET["diagnosisDate"]);

$newDiag = DiagnosisInterface::insertPatientDiagnosis($patientId,$diagnosisId,$diagnosisDate);

//export the diagnosis to external systems
// $mrn = Patient::getPatientById($patientId)->patientId;
// if($mrn !== NULL) {
//     Opal::insertPatientDiagnosis(
//         $mrn,
//         $newDiag->diagnosis->id, // $newDiag->id,
//         $newDiag->diagnosis->subcode,
//         $newDiag->createdDate,
//         $newDiag->diagnosis->subcodeDescription,
//         ""
//     );
// }

?>
