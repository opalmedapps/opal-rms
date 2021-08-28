<?php

declare(strict_types=1);
//====================================================================================
// php code to query the database and extract the list of patients
// who are currently checked in for open appointments today, using a selected list of destination rooms/areas
//====================================================================================

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$examRooms = $params["examRooms"] ?? [];

if($examRooms === []) {
    Http::generateResponseJsonAndExit(200, data: []);
}

$occupants = HospitalInterface::getOccupantsForExamRooms($examRooms);
$occupants = array_map(fn($x) => [
    "Name"              => $x["name"],
    "ArrivalDateTime"   => $x["arrival"],
    "PatientId"         => $x["patientId"],
    "PatientName"       => $x["patientName"],
],$occupants);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($occupants));
