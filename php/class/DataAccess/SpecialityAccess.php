<?php

declare(strict_types=1);

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
    public static function getSpecialityGroups(): array
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

    /**
     *
     * @return list<array{
     *      clinicHubId: int,
     *      clinicHubName: string,
     *      specialityGroupId: int,
     *      specialityGroupName: string
     * }>
     */
    public static function getHubs(): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                CH.ClinicHubId,
                CH.ClinicHubName,
                SG.SpecialityGroupId,
                SG.SpecialityGroupName
            FROM
                ClinicHub CH
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CH.SpecialityGroupId
            ORDER BY
                SG.SpecialityGroupName,
                CH.ClinicHubName
        ");
        $query->execute();

        return array_map(fn($x) => [
            "clinicHubId"           => (int) $x["ClinicHubId"],
            "clinicHubName"         => (string) $x["ClinicHubName"],
            "specialityGroupId"     => (int) $x["SpecialityGroupId"],
            "specialityGroupName"   => (string) $x["SpecialityGroupName"],
        ], $query->fetchAll());
    }

}
