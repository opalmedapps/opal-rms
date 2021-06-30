<?php declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;
use Orms\Patient\PatientInterface;
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
    dateOfBirth:        $fields["dateOfBirth"],
    ramq:               $fields["ramq"] ?? NULL,
    ramqExpiration:     $fields["ramqExpiration"] ?? NULL,
    mrns:               array_map(fn($x) => new ApiMrn(mrn: $x["mrn"],site: $x["site"],active: $x["active"]),$fields["mrns"])
) {
    public DateTime $dateOfBirth;
    public ?DateTime $ramqExpiration;

    function __construct(
        public string $firstName,
        public string $lastName,
        string $dateOfBirth,
        public ?string $ramq,
        ?string $ramqExpiration,
        /** @var ApiMrn[] $mrns */ public array $mrns
    ) {
        $this->dateOfBirth = DateTime::createFromFormatN("Y-m-d H:i:s",$dateOfBirth) ?? throw new Exception("Invalid date of birth");
        $this->ramqExpiration = DateTime::createFromFormatN("Y-m-d H:i:s",$ramqExpiration ?? "");
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

//for each of the mrns, check to see if the patient exists in the system
$patients = getPatientsFromMrns($demographics->mrns);

//case 1: none of the patient's mrns exist in the system
//create the patient
if($patients === [])
{
    /** @psalm-suppress ArgumentTypeCoercion */
    $patient = PatientInterface::insertNewPatient(
        $demographics->firstName,
        $demographics->lastName,
        $demographics->dateOfBirth,
        array_map(fn($x) => ["mrn" => $x->mrn,"site" => $x->site,"active" => $x->active],$demographics->mrns),
        []
    );

    if($demographics->ramq !== NULL && $demographics->ramqExpiration !== NULL) {
        $patient = PatientInterface::updateInsurances($patient,[["number" => $demographics->ramq,"expiration" => $demographics->ramqExpiration,"type" => "RAMQ","active" => TRUE]]);
    }
}
//case 2: all mrns belong to the same patient
//update the patient's demographics
elseif(count($patients) === 1)
{
    //all patients in the array are the same, so just use the first one
    $patient = $patients[0];

    $patient = PatientInterface::updateName($patient,$demographics->firstName,$demographics->lastName);
    $patient = PatientInterface::updateDateOfBirth($patient,$demographics->dateOfBirth);
    /** @psalm-suppress ArgumentTypeCoercion */
    $patient = PatientInterface::updateMrns($patient,array_map(fn($x) => ["mrn" => $x->mrn,"site" => $x->site,"active" => $x->active],$demographics->mrns));

    if($demographics->ramq !== NULL && $demographics->ramqExpiration !== NULL) {
        $patient = PatientInterface::updateInsurances($patient,[["number" => $demographics->ramq,"expiration" => $demographics->ramqExpiration,"type" => "RAMQ","active" => TRUE]]);
    }
}
//case 3: mrns belong to different patients
//this usually happens because link or merge was done for an mrn in the patients hospital chart
//merge patient and patient related information
// else
// {
//     //repeat the merge until all mrns belong to one patient
//     while(count($patients) > 1)
//     {
//         PatientInterface::mergePatientEntries($patients[0],$patients[1]);
//         $patients = getPatientsFromMrns($demographics->mrns);
//     }
// }

//case 4: incorrect information given about patients (delete ramqs and the like)
//  make separate api?

Http::generateResponseJsonAndExit(200);

/**
 *
 * @param ApiMrn[] $mrns
 * @return Patient[]
 * @phpstan-ignore-next-line
 */
function getPatientsFromMrns(array $mrns): array
{
    $patients = array_map(function($x) {
        return PatientInterface::getPatientByMrn($x->mrn,$x->site);
    },$mrns);

    $patients = array_filter($patients); //filter nulls
    $patients = array_unique($patients,SORT_REGULAR); //filter duplicates
    usort($patients,fn($a,$b) => $a->id <=> $b->id);  //sort by oldest record first in case of merge

    return $patients;
}
