<?php declare(strict_types=1);

//updates the opal status (ie whether the patient is an opal patient) of a patient

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;

try {
    $fields = Http::parseApiInputs();
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$opal = new class(
    mrn:         $fields["mrn"],
    site:        $fields["site"],
    opalStatus:  $fields["opalStatus"],
) {
    function __construct(
        public string $mrn,
        public string $site,
        public int $opalStatus
    ) {}
};

$patient = Patient::getPatientByMrn($opal->mrn,$opal->site);

if($patient === NULL)  {
    Http::generateResponseJsonAndExit(400,error: "Patient not found");
}

Patient::updateOpalStatus($patient,$opal->opalStatus);

Http::generateResponseJsonAndExit(200);
