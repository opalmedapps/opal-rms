<?php

// SPDX-FileCopyrightText: Copyright (C) 2019 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Patient\Model;

use Orms\DateTime;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;

/** @psalm-immutable */
class Patient
{
    /**
     * Only use in PatientInterface class and DataAccess layer.
     */
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        public string $sex,
        public ?string $phoneNumber,
        public int $opalStatus,
        public string $opalUUID,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */
        public array $mrns,
        /** @var Insurance[] $insurances */
        public array $insurances
    ) {}

    /**
     *
     * @return Mrn[]
     */
    public function getActiveMrns(): array
    {
        $mrns = array_values(array_filter($this->mrns, fn($x) => $x->active === true));

        //sort the mrns to guarantee that they're always in the same order
        usort($mrns, fn($a, $b) => [$a->mrn,$a->site] <=> [$b->mrn,$b->site]);

        return $mrns;
    }

}
