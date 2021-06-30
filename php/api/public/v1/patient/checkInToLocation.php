<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Appointment\Location;

try {
    $fields = Http::parseApiInputs();
    $checkIn = new class(
        mrn:   $fields["mrn"],
        site:  $fields["site"],
        room:  $fields["room"],
    ) {
        function __construct(
            public string $mrn,
            public string $site,
            public string $room
        ) {}
    };
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$patient = PatientInterface::getPatientByMrn($checkIn->mrn,$checkIn->site);

if($patient === NULL)  {
    Http::generateResponseJsonAndExit(400,error: "Patient not found");
}

Location::movePatientToLocation($patient,$checkIn->room);

Http::generateResponseJsonAndExit(200);
