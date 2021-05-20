<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;
use Orms\Hospital\MUHC\WebServiceInterface;

$ramq = $_GET["ramq"] ?? NULL;
$mrn  = $_GET["mrn"] ?? NULL;
$site = $_GET["site"] ?? NULL;

//use the http params to fetch the patient from ORMS
$patient = NULL;
if($mrn !== NULL && $site !== NULL) {
    $patient = Patient::getPatientByMrn($mrn,$site);
}
elseif($ramq !== NULL) {
    $patient = Patient::getPatientByInsurance($ramq,"RAMQ");
}

//if the patient was found, return it
//otherwise look in the ADT to find the patient
if($patient !== NULL)
{
    $searchedMrn = array_values(array_filter($patient->mrns,fn($x) => $x->mrn === $mrn && $x->site === $site))[0];

    $response =  [[
        "last"      => $patient->lastName,
        "first"     => $patient->firstName,
        "ramq"      => array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->number ?? NULL,
        "ramqExp"   => (array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->expiration ?? NULL)?->modifyN("first day of")?->format("Y-m-d"),
        "mrn"       => $searchedMrn->mrn,
        "site"      => $searchedMrn->site,
        "active"    => $searchedMrn->active
    ]];

    Http::generateResponseJsonAndExit(200,$response);
}

//in theory, it's possible to get multiple patients back from the adt (we don't control it)

$patients = [];
if($mrn !== NULL && $site !== NULL) {
    $patients = WebServiceInterface::findPatientByMrnAndSite($mrn,$site);
}
elseif($ramq !== NULL) {
    $patients = WebServiceInterface::findPatientByRamq($ramq);
}

$patients = array_map(function($x) use($mrn,$site) {
    $searchedMrn = array_values(array_filter($x->mrns,fn($x) => $x->mrn === $mrn && $x->mrnType === $site))[0];

    return [
        "last"      => $x->lastName,
        "first"     => $x->firstName,
        "ramq"      => $x->ramqNumber,
        "ramqExp"   => $x->ramqExpDate,
        "mrn"       => $searchedMrn->mrn,
        "site"      => $searchedMrn->mrnType,
        "active"    => $searchedMrn->active
    ];
},$patients);

Http::generateResponseJsonAndExit(200,$patients);
