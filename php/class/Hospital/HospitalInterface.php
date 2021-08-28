<?php

declare(strict_types=1);

namespace Orms\Hospital;

use Orms\DataAccess\HospitalAccess;

class HospitalInterface
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
        return HospitalAccess::getHospitalSites();
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
        return HospitalAccess::getSpecialityGroups();
    }

    /**
     *
     * @return array<string,list<array{
     *      clinicHubId: int,
     *      clinicHubName: string,
     *      specialityGroupId: int,
     *      specialityGroupName: string
     * }>>
     */
    public static function getHubs(): array
    {
        $hubs = HospitalAccess::getHubs();

        $specialityGroups = array_reduce($hubs, function($x, $y) {
            $x[$y["specialityGroupName"]][] = [
                "clinicHubId"           => (int) $y["clinicHubId"],
                "clinicHubName"         => (string) $y["clinicHubName"],
                "specialityGroupId"     => (int) $y["specialityGroupId"],
                "specialityGroupName"   => (string) $y["specialityGroupName"],
            ];

            return $x;
        }, []);

        return $specialityGroups;
    }

}
