<?php declare(strict_types=1);

//updates the opal status (ie whether the patient is an opal patient) of a patient

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

$systemSite = Config::getApplicationSettings()->environment->site;

if($opal->site !== $systemSite) {
    Http::generateResponseJsonAndExit(400,error: "Site is not supported");
}

$patient = Patient::getPatientByMrn($opal->mrn);

if($patient === NULL)  {
    Http::generateResponseJsonAndExit(400,error: "Patient not found");
}

Patient::updateOpalStatus($patient,$opal->opalStatus);

Http::generateResponseJsonAndExit(200);

?>
