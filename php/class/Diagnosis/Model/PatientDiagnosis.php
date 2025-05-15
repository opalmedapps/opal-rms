<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Diagnosis\Model;

use Orms\DateTime;
use Orms\Diagnosis\Model\Diagnosis;

/** @psalm-immutable */
/** @psalm-suppress PossiblyUnusedProperty */
class PatientDiagnosis
{
    public function __construct(
        public int $id,
        public int $patientId,
        public string $status,
        public DateTime $diagnosisDate,
        public DateTime $createdDate,
        public DateTime $updatedDate,
        public Diagnosis $diagnosis,
    ) {}
}
