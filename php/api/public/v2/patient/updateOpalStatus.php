<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//updates the opal status (ie whether the patient is an opal patient) of a patient

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs('v2');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$opal = new class(
    mrn:         $fields["mrn"],
    site:        $fields["site"],
    opalStatus:  $fields["opalStatus"],
    opalUUID:    $fields["opalUUID"] ?? null,
) {
    public function __construct(
        public string $mrn,
        public string $site,
        public int $opalStatus,
        public ?string $opalUUID,
    ) {}
};

// Validate input
if ($opal->opalStatus == 1 && is_null($opal->opalUUID)) {
    Http::generateResponseJsonAndExit(400, error: "If opalStatus is 1, an opalUUID must be provided.");
}
else if ($opal->opalStatus == 0 && !is_null($opal->opalUUID)) {
    Http::generateResponseJsonAndExit(400, error: "If opalStatus is 0, an opalUUID should not be provided.");
}

$patient = PatientInterface::getPatientByMrn($opal->mrn, $opal->site);

if ($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

// Clear the patient's Opal UUID if their OpalStatus is being set to 0
if ($opal->opalStatus == 0) $opal->opalUUID = '';

PatientInterface::updatePatientInformation($patient, opalStatus: $opal->opalStatus, opalUUID: $opal->opalUUID);

Http::generateResponseJsonAndExit(200);
