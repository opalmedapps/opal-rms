<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

/** @psalm-immutable */
class ExternalMrn
{
    function __construct(
        public string $mrn,
        public string $site,
        public bool $active,
    ) {}
}