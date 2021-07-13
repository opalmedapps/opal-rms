<?php declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\ApplicationException;
use Orms\Http;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\Mrn;
use Orms\Patient\PatientInterface;
use Orms\DateTime;
use Orms\Patient\Model\Insurance;
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
    sex:                $fields["sex"],
    mrns:               array_map(fn($x) => new Mrn(mrn: $x["mrn"],site: $x["site"],active: $x["active"]),$fields["mrns"]),
    insurances:         array_map(fn($x) => new Insurance(
                            $x["insuranceNumber"],
                            DateTime::createFromFormatN("Y-m-d H:i:s",$x["expirationDate"]) ?? throw new Exception("Invalid insurance expiration date"),
                            $x["type"],
                            $x["active"]
                        ),$fields["insurances"] ?? [])
) {
    public DateTime $dateOfBirth;

    function __construct(
        public string $firstName,
        public string $lastName,
        string $dateOfBirth,
        public string $sex,
        /** @var Mrn[] $mrns */ public array $mrns,
        /** @var Insurance[] $insurances */ public array $insurances
    ) {
        $this->dateOfBirth = DateTime::createFromFormatN("Y-m-d H:i:s",$dateOfBirth) ?? throw new Exception("Invalid date of birth");
    }
};

//for each of the mrns, check to see if the patient exists in the system
$patients = getPatientsFromMrns($demographics->mrns);

try
{
    //case 1: none of the patient's mrns exist in the system
    //create the patient
    if($patients === [])
    {
        $patient = PatientInterface::insertNewPatient(
            firstName:   $demographics->firstName,
            lastName:    $demographics->lastName,
            dateOfBirth: $demographics->dateOfBirth,
            sex:         $demographics->sex,
            mrns:        $demographics->mrns,
            insurances:  $demographics->insurances
        );
    }
    //case 2: all mrns belong to the same patient
    //update the patient's demographics
    elseif(count($patients) === 1)
    {
        //all patients in the array are the same, so just use the first one
        $patient = PatientInterface::updatePatientInformation(
            patient:     $patients[0],
            firstName:   $demographics->firstName,
            lastName:    $demographics->lastName,
            dateOfBirth: $demographics->dateOfBirth,
            sex:         $demographics->sex,
            mrns:        $demographics->mrns,
            insurances:  $demographics->insurances
        );
    }
    //case 3: mrns belong to different patients
    //this usually happens because link or merge was done for an mrn in the patients hospital chart
    //merge patient and patient related information
    else
    {
        //repeat the merge until all mrns belong to one patient
        while(count($patients) > 1)
        {
            PatientInterface::mergePatientEntries($patients[0],$patients[1]);
            $patients = getPatientsFromMrns($demographics->mrns);
        }
    }

    //case 4: incorrect information given about patients (delete insurances and the like)
    //  make separate api?
}
catch(ApplicationException $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

Http::generateResponseJsonAndExit(200);

/**
 *
 * @param Mrn[] $mrns
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
