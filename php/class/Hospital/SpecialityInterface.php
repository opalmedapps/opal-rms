<?php declare(strict_types = 1);

namespace Orms\Hospital;

use Orms\DataAccess\SpecialityAccess;

class SpecialityInterface
{
    /**
     * Returns all speciality groups in the system.
     * @return list<array{
     *      specialityCode: string,
     *      specialityName: string
     * }>
     */
    static function getSpecialityGroups(): array
    {
        return SpecialityAccess::getSpecialityGroups();
    }

}
