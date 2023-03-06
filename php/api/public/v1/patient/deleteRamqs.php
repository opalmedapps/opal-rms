<?php

declare(strict_types=1);

//disables all a patients ramqs
//this api is because the OIE cannot detect which of a patient ramqs was deleted, only that one was

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$potentialPatient = new class(
    mrn:           $fields["mrn"],
    site:          $fields["site"],
    insuranceType: $fields["insuranceType"],
) {
    public function __construct(
        public string $mrn,
        public string $site,
        public string $insuranceType,
    ) {}
};

$patient = PatientInterface::getPatientByMrn($potentialPatient->mrn, $potentialPatient->site);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

PatientInterface::deactivateInsurance($patient,$potentialPatient->insuranceType);

Http::generateResponseJsonAndExit(200);
