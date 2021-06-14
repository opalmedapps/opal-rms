<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use Orms\DateTime;
use Orms\Hospital\OIE\Internal\ExternalMrn;
use Orms\Hospital\OIE\Internal\ExternalInsurance;

/** @psalm-immutable */
class ExternalPatient
{
    function __construct(
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        /** @var ExternalMrn[] $mrns */ public array $mrns,
        /** @var ExternalInsurance[] $insurances */ public array $insurances
    ) {}
}
