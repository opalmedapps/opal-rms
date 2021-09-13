<?php

declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\ApplicationException;
use Orms\DateTime;
use Orms\Http;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Patient;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

//the input is an array of json patient objects
//the first entry is the original patient and the second in the unlinked one
$demographics = array_map(fn($p) => new class(
    firstName:          $p["firstName"],
    lastName:           $p["lastName"],
    dateOfBirth:        $p["dateOfBirth"],
    sex:                $p["sex"],
    mrns:               array_map(fn($x) => new Mrn(mrn: $x["mrn"], site: $x["site"], active: $x["active"]), $p["mrns"]),
    insurances:         array_map(fn($x) => new Insurance(
        $x["insuranceNumber"],
        DateTime::createFromFormatN("Y-m-d H:i:s", $x["expirationDate"]) ?? throw new Exception("Invalid insurance expiration date"),
        $x["type"],
        $x["active"]
    ), $p["insurances"] ?? [])
) {
    public DateTime $dateOfBirth;

    public function __construct(
        public string $firstName,
        public string $lastName,
        string $dateOfBirth,
        public string $sex,
        /** @var Mrn[] $mrns */
        public array $mrns,
        /** @var Insurance[] $insurances */
        public array $insurances
    ) {
        $this->dateOfBirth = DateTime::createFromFormatN("Y-m-d H:i:s", $dateOfBirth) ?? throw new Exception("Invalid date of birth");
    }
},$fields);

//for each of the mrns, check to see if the patients exists in the system
$originalPatients = PatientInterface::getPatientsFromMrns($demographics[0]->mrns);
$unlinkedPatients = PatientInterface::getPatientsFromMrns($demographics[1]->mrns);

//many cases are possible here, each has to be treated individually

try {
    //at the very minimum, we'll assume that all the mrns a patient has belong to at most one patient entry, or else things get very complicated
    if(count($originalPatients) > 1 || count($unlinkedPatients) > 1) {
        throw new ApplicationException(ApplicationException::ABERRANT_PATIENT_CHART,"Patient's chart is convoluted; must be manually examined");
    }

    //case 1: original and unlinked patients are the same patient entity
    if($originalPatients !== [] && $unlinkedPatients !== [] && $originalPatients[0]->id === $unlinkedPatients[0]->id) {
        //update the original patient
        PatientInterface::updatePatientInformation(
            patient:     $originalPatients[0],
            firstName:   $demographics[0]->firstName,
            lastName:    $demographics[0]->lastName,
            dateOfBirth: $demographics[0]->dateOfBirth,
            sex:         $demographics[0]->sex,
            mrns:        $demographics[0]->mrns,
            insurances:  $demographics[0]->insurances
        );

        //unlink the new patient's mrns from the original
        PatientInterface::unmergePatientEntries(
            originalEntry:  $originalPatients[0],
            firstName:      $demographics[1]->firstName,
            lastName:       $demographics[1]->lastName,
            dateOfBirth:    $demographics[1]->dateOfBirth,
            sex:            $demographics[1]->sex,
            mrns:           $demographics[1]->mrns,
            insurances:     $demographics[1]->insurances
        );
    }

    //case 2: none of the mrns exists in the system
    //case 3: the original patient doesn't exist, but the unlinked does
    //case 4: the original patient exists, but the unlinked doesn't
    //case 5: the original patient and the unlinked one have already been unlinked (that is, $originalPatients !== [] && $unlinkedPatients !== [] && $originalPatients[0]->id !== $unlinkedPatients[0]->id)

    //in each of these cases, the patient (original or unlinked) doesn't exist in the system, we add them; otherwise we just update the existing entry
    else {
        foreach([$originalPatients,$unlinkedPatients] as $index => $p)
        {
            if($p === []) {
                PatientInterface::insertNewPatient(
                    firstName:   $demographics[$index]->firstName,
                    lastName:    $demographics[$index]->lastName,
                    dateOfBirth: $demographics[$index]->dateOfBirth,
                    sex:         $demographics[$index]->sex,
                    mrns:        $demographics[$index]->mrns,
                    insurances:  $demographics[$index]->insurances
                );
            }
            else {
                PatientInterface::updatePatientInformation(
                    patient:     $p[0],
                    firstName:   $demographics[$index]->firstName,
                    lastName:    $demographics[$index]->lastName,
                    dateOfBirth: $demographics[$index]->dateOfBirth,
                    sex:         $demographics[$index]->sex,
                    mrns:        $demographics[$index]->mrns,
                    insurances:  $demographics[$index]->insurances
                );
            }
        }
    }
}
catch(ApplicationException $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

Http::generateResponseJsonAndExit(200);
