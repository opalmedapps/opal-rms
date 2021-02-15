<?php declare(strict_types=1);

//checks if a patient with an mrn + site combination exists in the database and return json true or false

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient;
use Orms\Config;

try {
    $fields = Http::parseApiInputs();
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$potentialPatient = new class(
    mrn:  $fields["mrn"],
    site: $fields["site"],
) {
    function __construct(
        public string $mrn,
        public string $site
    ) {}
};

$systemSite = Config::getApplicationSettings()->environment->site;

$patientFound = FALSE;

if($potentialPatient->site === $systemSite)
{
    $patient = Patient::getPatientByMrn($potentialPatient->mrn);
    if($patient !== NULL) $patientFound = TRUE;
}

Http::generateResponseJsonAndExit(200,$patientFound);
