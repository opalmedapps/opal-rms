<?php

declare(strict_types=1);

//set a patient's mrn to inactive

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Model\Mrn;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$mrn = new class(
    mrn:         $fields["mrn"],
    site:        $fields["site"]
) {
    public function __construct(
        public string $mrn,
        public string $site,
    ) {}
};

$patient = PatientInterface::getPatientByMrn($mrn->mrn,$mrn->site);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

PatientInterface::updatePatientInformation($patient,mrns: [new Mrn($mrn->mrn,$mrn->site,false)]);

Http::generateResponseJsonAndExit(200);
