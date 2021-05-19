<?php declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;
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
    /** @var ApiMrn[] $mrns */ public array $mrns;

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

        $this->mrns = array_map(fn($x) => new ApiMrn(mrn: $x["mrn"],site: $x["site"],active: $x["active"]),$mrns);
    }
};

class ApiMrn
{
    function __construct(
        public string $mrn,
        public string $site,
        public bool $active
    ) {}
}

//see if the patient exists in ORMS with any of the mrns
$patient = NULL;
foreach($demographics->mrns as $mrn)
{
    $patient = Patient::getPatientByMrn($mrn->mrn,$mrn->site);
    if($patient !== NULL) break;
}

//if not, create the patient in the system and fill out their information
//otherwise, update the demographic info
if($patient === NULL) {
    $patient = Patient::insertNewPatient(
        $demographics->firstName,
        $demographics->lastName,
        array_map(function($x) {
            return [
                "mrn"    => $x->mrn,
                "site"   => $x->site,
                "active" => $x->active
            ];
        },$demographics->mrns)
    );
}
else {
    $patient = Patient::updateName($patient,$demographics->firstName,$demographics->lastName);
}

if($demographics->ramq !== NULL && $demographics->ramqExpiration !== NULL) {
    $patient = Patient::updateInsurance($patient,$demographics->ramq,"RAMQ",$demographics->ramqExpiration,TRUE);
}

Http::generateResponseJsonAndExit(200);

?>
