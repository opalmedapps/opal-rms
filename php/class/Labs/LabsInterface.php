<?php

declare(strict_types=1);

namespace Orms\Labs;

use Orms\DataAccess\LabsAccess;
use Orms\DateTime;
use Orms\Labs\Model\Labs;

class LabsInterface
{
    /**
     *
     * @return Labs[]
     */
    public static function getLabsListForPatient(?string $patientId = null): array
    {
        return LabsAccess::getLabsListForPatient($patientId);
    }
}
