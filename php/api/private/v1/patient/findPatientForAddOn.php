<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient;
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
if($patient !== NULL) {
    $response =  [[
        "last"      => $patient->lastName,
        "first"     => $patient->firstName,
        "ramq"      => array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->number ?? NULL,
        "ramqExp"   => (array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->expiration ?? NULL)?->modifyN("first day of")?->format("Y-m-d"),
        "mrn"       => $patient->mrns[0]->mrn,
        "site"      => $patient->mrns[0]->site
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

$patients = array_map(function($x) {
    return [
        "last"      => $x->lastName,
        "first"     => $x->firstName,
        "ramq"      => $x->ramqNumber,
        "ramqExp"   => $x->ramqExpDate,
        "mrn"       => $x->mrns[0]->mrn,
        "site"      => $x->mrns[0]->mrnType
    ];
},$patients);

Http::generateResponseJsonAndExit(200,$patients);

?>
