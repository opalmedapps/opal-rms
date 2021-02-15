<?php declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Config;
use Orms\Patient;
use Orms\DateTime;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$demographics = new class(
    firstName:          $fields["firstName"],
    lastName:           $fields["lastName"],
    ramq:               $fields["ramq"] ?? NULL,
    ramqExpiration:     $fields["ramqExpiration"] ?? NULL,
    mrns:               $fields["mrns"]
) {
    public ?DateTime $ramqExpiration;
    /** @var Mrn[] $mrns */ public array $mrns;

    /**
     *
     * @param mixed[] $mrns
     */
    function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $ramq,
        ?string $ramqExpiration,
        array $mrns
    ) {
        $this->ramqExpiration = DateTime::createFromFormatN("Y-m-d H:i:s",$ramqExpiration ?? "");

        $this->mrns = array_map(fn($x) => new Mrn(mrn: $x["mrn"],site: $x["site"],active: $x["active"]),$mrns);
    }
};

class Mrn
{
    function __construct(
        public string $mrn,
        public string $site,
        public int $active
    ) {}
}

//use the site and mrn to check if the patient exists
$systemSite = Config::getApplicationSettings()->environment->site;
$mrn = array_values(array_filter($demographics->mrns,fn($x) => $x->site === $systemSite && $x->active === 1))[0] ?? NULL;

if($mrn === NULL) {
    Http::generateResponseJsonAndExit(400,error: "No valid mrn for $systemSite");
}

//see if the patient exists in ORMS
$patient = Patient::getPatientByMrn($mrn->mrn);

if($patient === NULL)
{
    //if the patient doesn't, an expired mrn may be in the system
    $expiredMrn = array_values(array_filter($demographics->mrns,fn($x) => $x->site === $systemSite && $x->active === 0))[0] ?? [];

    if($expiredMrn !== []) $patient = Patient::getPatientByMrn($expiredMrn->mrn);
}

if($patient === NULL) {
    Patient::insertNewPatient($demographics->firstName,$demographics->lastName,$mrn->mrn,$demographics->ramq,$demographics->ramqExpiration);
}
else {
    Patient::updateDemographics($patient->id,$demographics->firstName,$demographics->lastName,$mrn->mrn,$demographics->ramq,$demographics->ramqExpiration);
}

Http::generateResponseJsonAndExit(200);

?>
