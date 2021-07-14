<?php declare(strict_types = 1);

namespace Orms\Patient\Model;

use Orms\DateTime;

/** @psalm-immutable */
class PatientMeasurement
{
    function __construct(
        public string $id,
        public string $appointmentId,
        public string $mrnSite,
        public DateTime $datetime,
        public float $weight,
        public float $height,
        public float $bsa
    ) {}
}
