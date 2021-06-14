<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use Orms\DateTime;

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
