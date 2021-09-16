<?php

declare(strict_types=1);

namespace Orms\Patient\Model;

/** @psalm-immutable */
class Mrn
{
    public function __construct(
        public string $mrn,
        public string $site,
        public bool $active
    ) {}
}
