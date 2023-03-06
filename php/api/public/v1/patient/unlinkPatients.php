<?php

declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\ApplicationException;
use Orms\Http;
use Orms\Patient\Model\Mrn;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs('v1');
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

//the input is an array of json mrn objects
//the first entry is the one mrn from the original patient and the second one is an mrn from the unlinked one
$mrns = array_map(fn($x) => new Mrn(mrn: $x["mrn"], site: $x["site"], active: $x["active"]), $fields);

//for each of the mrns, check to see if the patients exists in the system
$originalPatient = PatientInterface::getPatientByMrn($mrns[0]->mrn,$mrns[0]->site);
$linkedPatient = PatientInterface::getPatientByMrn($mrns[1]->mrn,$mrns[1]->site);

//many cases are possible here, each has to be treated individually
//in theory, the OIE checks to see if the mrns exists in the system and only calls this api if both exist

try {
    //case 1: none of the mrns exists in the system
    //case 2: the original patient exists, but the unlinked doesn't
    //case 3: the original patient doesn't exist, but the unlinked does
    //case 4: the original patient and the unlinked one have already been unlinked
    if(
        $originalPatient === null
        || $linkedPatient === null
        || ($originalPatient->id !== $linkedPatient->id)
    ) {
        //do nothing since either the original or the unlinked patients don't need to be touched (they don't share appointments, etc)
        Http::generateResponseJsonAndExit(200);
    }
    //case 5: original and unlinked patients are the same patient entity
    elseif($originalPatient->id === $linkedPatient->id) {
        //unlink the new patient's mrns from the original
        PatientInterface::unlinkPatientEntries($originalPatient,$mrns[1]);
    }
}
catch(ApplicationException $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

Http::generateResponseJsonAndExit(200);
