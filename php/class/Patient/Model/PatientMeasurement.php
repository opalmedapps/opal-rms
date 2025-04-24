<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Patient\Model;

use Orms\DateTime;

/** @psalm-immutable */
/** @psalm-suppress PossiblyUnusedProperty */
class PatientMeasurement
{
    public function __construct(
        public string $id,
        public string $appointmentId,
        public string $mrnSite,
        public DateTime $datetime,
        public float $weight,
        public float $height,
        public float $bsa
    ) {}
}
