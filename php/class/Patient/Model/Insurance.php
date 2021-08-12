<?php

declare(strict_types=1);

namespace Orms\Patient\Model;

use Orms\DateTime;

/** @psalm-immutable */
class Insurance
{
    public function __construct(
        public string $number,
        public DateTime $expiration,
        public string $type,
        public bool $active
    ) {}
}
