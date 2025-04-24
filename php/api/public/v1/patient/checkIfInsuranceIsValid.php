<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//checks if a patient with an mrn + site combination exists in the database and return json true or false

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$insurance = new class(
    insuranceNumber:  $fields["insuranceNumber"],
    type:             $fields["type"],
) {
    public function __construct(
        public string $insuranceNumber,
        public string $type
    ) {}
};

$valid = PatientInterface::isInsuranceValid($insurance->insuranceNumber, $insurance->type);

Http::generateResponseJsonAndExit(200, data: $valid);
