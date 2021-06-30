<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

$ramq = $_GET["ramq"] ?? NULL;
$mrn  = $_GET["mrn"] ?? NULL;
$site = $_GET["site"] ?? NULL;

//use the http params to fetch the patient from ORMS
$patient = NULL;
if($mrn !== NULL && $site !== NULL) {
    $patient = PatientInterface::getPatientByMrn($mrn,$site);
}
elseif($ramq !== NULL) {
    $patient = PatientInterface::getPatientByInsurance($ramq,"RAMQ");
}

//if the patient was found, return it
if($patient !== NULL)
{
    //if the patient searched with mrn + site, only display that mrn
    //if they searched by ramq, display all mrns the patient has
    $displayMrns = array_filter($patient->mrns,fn($x) => $x->mrn === $mrn && $x->site === $site);
    if($displayMrns === []) {
        $displayMrns = $patient->mrns;
    }

    $response = [];
    foreach($displayMrns as $mrn)
    {
        $response[] = [
            "last"      => $patient->lastName,
            "first"     => $patient->firstName,
            "ramq"      => array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->number ?? NULL,
            "ramqExp"   => (array_values(array_filter($patient->insurances,fn($x) => $x->type === "RAMQ"))[0]->expiration ?? NULL)?->modifyN("first day of")?->format("Y-m-d"),
            "mrn"       => $mrn->mrn,
            "site"      => $mrn->site,
            "active"    => $mrn->active
        ];
    }

    Http::generateResponseJsonAndExit(200,$response);
}

//if no patient was found, just return an empty array
Http::generateResponseJsonAndExit(200,[]);
