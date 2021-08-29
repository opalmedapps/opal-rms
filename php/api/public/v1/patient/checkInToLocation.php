<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\LocationInterface;
use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs();
    $checkIn = new class(
        mrn:   $fields["mrn"],
        site:  $fields["site"],
        room:  $fields["room"],
    ) {
        public function __construct(
            public string $mrn,
            public string $site,
            public string $room
        ) {}
    };
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$patient = PatientInterface::getPatientByMrn($checkIn->mrn, $checkIn->site);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

LocationInterface::movePatientToLocation($patient, $checkIn->room);

Http::generateResponseJsonAndExit(200);
