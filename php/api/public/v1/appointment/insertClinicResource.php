<?php

// SPDX-FileCopyrightText: Copyright (C) 2022 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__ . "/../../../../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\Http;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs('v1');
    $fields = Http::sanitizeRequestParams($fields);
    $fields = Encoding::utf8_decode_recursive($fields);

    $resource = new class(
        appointmentCode: $fields["appointmentCode"],
        clinics: $fields["clinics"],
        sourceSystem: $fields["sourceSystem"],
        specialityGroupCode: $fields["specialityGroupCode"],
    ) {
        public string $clinicCode;
        public string $clinicDescription;

        /** @param mixed[] $clinics */
        public function __construct(
            public string $appointmentCode,
            array         $clinics,
            public string $sourceSystem,
            public string $specialityGroupCode,
        ) {
            $this->clinicCode = implode("; ", array_map(fn($x) => $x["clinicCode"], $clinics));
            $this->clinicDescription = implode("; ", array_map(fn($x) => $x["clinicDescription"], $clinics));
        }
    };

    AppointmentInterface::insertClinicResource(
        appointmentCode: $resource->appointmentCode,
        clinicCode: $resource->clinicCode,
        clinicDescription: $resource->clinicDescription,
        specialityGroupCode: $resource->specialityGroupCode,
        system: $resource->sourceSystem,
    );

} catch (\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

Http::generateResponseJsonAndExit(200);
