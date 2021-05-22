<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use Orms\DateTime;

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

/** @psalm-immutable */
class ExternalMrn
{
    function __construct(
        public string $mrn,
        public string $site,
        public bool $active,
    ) {}
}

/** @psalm-immutable */
class ExternalInsurance
{
    function __construct(
        public string $number,
        public DateTime $expiration,
        public string $type,
        public bool $active
    ) {}
}
