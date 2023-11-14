<?php

declare(strict_types=1);

namespace Orms\Labs\Model;

/** @psalm-immutable */
/** @psalm-suppress PossiblyUnusedProperty */
class Labs
{
    public function __construct(
        public int $test_result_id,
        public \DateTime $specimen_collected_date,
        public \DateTime $component_result_date,
        public ?string $test_group_name,
        public ?int $test_component_sequence,
        public ?string $test_component_name,
        public ?float $test_value,
        public ?string $test_units,
        public ?float $max_norm_range,
        public ?float $min_norm_range,
        public ?string $abnormal_flag,
    ) {}
}
