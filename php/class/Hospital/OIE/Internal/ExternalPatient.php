<?php

declare(strict_types=1);

namespace Orms\Hospital\OIE\Internal;

use Orms\DateTime;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;

/** @psalm-immutable */
class ExternalPatient
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        /** @var Mrn[] $mrns */
        public array $mrns,
        /** @var Insurance[] $insurances */
        public array $insurances
    ) {}
}
