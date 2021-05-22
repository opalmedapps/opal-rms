<?php declare(strict_types=1);

//api endpoint that accepts patient demographics information
//if the patient exists, the patient is updated, otherwise the patient is inserted into the db

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;
use Orms\Hospital\OIE\Fetch;

$params = Http::getPostContents();

$mrn    = $params["mrn"] ?? NULL;
$site   = $params["site"] ?? NULL;

if($mrn === NULL || $site === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Invalid inputs");
}

//find patient in ADT
$externalPatient = Fetch::getExternalPatientByMrnAndSite($mrn,$site);

if($externalPatient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Could not find patient in ADT");
}

//insert patient
/** @psalm-suppress ArgumentTypeCoercion */
$patient = Patient::insertNewPatient(
    $externalPatient->firstName,
    $externalPatient->lastName,
    $externalPatient->dateOfBirth,
    array_map(function($x) {
        return [
            "mrn"      => $x->mrn,
            "site"     => $x->site,
            "active"   => $x->active
        ];
    },$externalPatient->mrns)
);

foreach($externalPatient->insurances as $insurance) {
    $patient = Patient::updateInsurance(
        $patient,
        $insurance->number,
        $insurance->type,
        $insurance->expiration,
        $insurance->active
    );
}

Http::generateResponseJsonAndExit(200);
