<?php

declare(strict_types=1);

namespace Orms\Diagnosis\Model;

use Orms\DateTime;
use Orms\Diagnosis\Model\Diagnosis;

/** @psalm-immutable */
class PatientDiagnosis
{
    public function __construct(
        public int $id,
        public int $patientId,
        public string $status,
        public DateTime $diagnosisDate,
        public DateTime $createdDate,
        public DateTime $updatedDate,
        public Diagnosis $diagnosis,
    ) {}
}
