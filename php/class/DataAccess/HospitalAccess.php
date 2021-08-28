<?php

declare(strict_types=1);

namespace Orms\DataAccess;

class HospitalAccess
{
    /**
     *
     * @return list<array{
     *  hospitalCode: string,
     *  hospitalName: string
     * }>
     */
    public static function getHospitalSites(): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                HospitalCode,
                HospitalName
            FROM
                Hospital
        ");
        $query->execute();

        return array_map(fn($x) => [
            "hospitalCode" => $x["HospitalCode"],
            "hospitalName" => $x["HospitalName"],
        ],$query->fetchAll());
    }

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
            "specialityCode"    => $x["SpecialityGroupCode"],
            "specialityName"    => $x["SpecialityGroupName"],
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
            "clinicHubName"         => $x["ClinicHubName"],
            "specialityGroupId"     => (int) $x["SpecialityGroupId"],
            "specialityGroupName"   => $x["SpecialityGroupName"],
        ], $query->fetchAll());
    }

}
