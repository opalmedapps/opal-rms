<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use Orms\DateTime;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Insurance;

/** @psalm-immutable */
class ExternalPatient
{
    function __construct(
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        /** @var Mrn[] $mrns */ public array $mrns,
        /** @var Insurance[] $insurances */ public array $insurances
    ) {}
}
