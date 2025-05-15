<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$ramq = $params["ramq"] ?? null;
$mrn  = $params["mrn"] ?? null;
$site = $params["site"] ?? null;

//use the http params to fetch the patient from ORMS
$patient = null;
if($mrn !== null && $site !== null) {
    $patient = PatientInterface::getPatientByMrn($mrn, $site);
}
elseif($ramq !== null) {
    $patient = PatientInterface::getPatientByInsurance($ramq, "RAMQ");
}

//if the patient was found, return it
if($patient !== null)
{
    //if the patient searched with mrn + site, only display that mrn
    //if they searched by ramq, display all mrns the patient has
    $displayMrns = array_filter($patient->mrns, fn($x) => $x->mrn === $mrn && $x->site === $site);
    if($displayMrns === []) {
        $displayMrns = $patient->mrns;
    }

    $response = [];
    foreach($displayMrns as $mrn)
    {
        $response[] = [
            "last"      => $patient->lastName,
            "first"     => $patient->firstName,
            "patientId" => $patient->id,
            "ramq"      => array_values(array_filter($patient->insurances, fn($x) => $x->type === "RAMQ"))[0]->number ?? null,
            "ramqExp"   => array_values(array_filter($patient->insurances, fn($x) => $x->type === "RAMQ"))[0]->expiration ?? null,
            "mrn"       => $mrn->mrn,
            "site"      => $mrn->site,
            "active"    => $mrn->active
        ];
    }

    Http::generateResponseJsonAndExit(200, $response);
}

//if no patient was found, just return an empty array
Http::generateResponseJsonAndExit(200, []);
