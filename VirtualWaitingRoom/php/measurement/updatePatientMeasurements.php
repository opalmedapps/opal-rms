<?php declare(strict_types=1);

//script to insert patient measurements in the database
//also exports the measurement update to other systems

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Patient\Patient;
use Orms\Patient\PatientMeasurement;
use Orms\Hospital\OIE\Export;

$patientId     = (int) ($_GET["patientId"] ?? NULL);
$height        = $_GET["height"] ?? NULL;
$weight        = $_GET["weight"] ?? NULL;
$bsa           = $_GET["bsa"] ?? NULL;
$sourceId      = $_GET["sourceId"] ?? NULL;
$sourceSystem  = $_GET["sourceSystem"] ?? NULL;

$patient = Patient::getPatientById($patientId);

if($patient === NULL) {
    http_response_code(400);
    exit("Unknown patient");
}

if($height === NULL
    || $weight === NULL
    || $bsa === NULL
    || $sourceId === NULL
    || $sourceSystem === NULL
) {
    http_response_code(400);
    exit("Incomplete measurements");
}

PatientMeasurement::insertMeasurement($patient,(float) $height,(float) $weight,(float) $bsa,$sourceId,$sourceSystem);

//print a message and close the connection so that the client does not wait
ob_start();
echo "Measurements inserted!\n";
header('Connection: close');
header('Content-Length: '.ob_get_length());
ob_end_flush();
ob_flush();
flush();

//send the update to external systems
Export::exportMeasurementPdf($patient);

?>
