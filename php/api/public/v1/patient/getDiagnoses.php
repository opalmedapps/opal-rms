<?php

// SPDX-FileCopyrightText: Copyright (C) 2022 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Diagnosis\DiagnosisInterface;
use Orms\Diagnosis\Model\PatientDiagnosis;
use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$mrn = new class(
    mrn:  $fields["mrn"],
    site: $fields["site"],
) {
    public function __construct(
        public string $mrn,
        public string $site
    ) {}
};

$patient = PatientInterface::getPatientByMrn($mrn->mrn, $mrn->site);

if($patient === null)  {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

$diagArr = DiagnosisInterface::getDiagnosisListForPatient($patient->id);
$diagArr = array_map(fn($x) => [
    "mrn"           => $mrn->mrn,
    "site"          => $mrn->site,
    "source"        => "ORMS",
    "rowId"         => $x->id,
    "externalId"    => "ICD-10",
    "code"          => $x->diagnosis->subcode,
    "creationDate"  => $x->createdDate->format("Y-m-d H:i:s"),
    "descriptionEn" => $x->diagnosis->subcodeDescription,
    "descriptionFr" => "",
    "status"        => $x->status
],$diagArr);

Http::generateResponseJsonAndExit(200,data: $diagArr);
