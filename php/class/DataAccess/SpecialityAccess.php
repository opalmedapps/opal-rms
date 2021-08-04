<?php declare(strict_types=1);

namespace Orms\DataAccess;

class SpecialityAccess
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
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                SpecialityGroupCode,
                SpecialityGroupName
            FROM
                SpecialityGroup
        ");
        $query->execute();

        return array_map(fn($x) => [
            "specialityCode"    => (string) $x["SpecialityGroupCode"],
            "specialityName"    => (string) $x["SpecialityGroupName"],
        ], $query->fetchAll());
    }

}
