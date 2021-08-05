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

    /**
     *
     * @return array<string,list<array{
     *      clinicHubId: int,
     *      clinicHubName: string,
     *      specialityGroupId: int,
     *      specialityGroupName: string
     * }>>
     */
    static function getHubs(): array
    {
        $hubs = SpecialityAccess::getHubs();

        $specialityGroups = array_reduce($hubs,function($x,$y) {
            $x[$y["specialityGroupName"]][] = [
                "clinicHubId"           => (int) $y["clinicHubId"],
                "clinicHubName"         => (string) $y["clinicHubName"],
                "specialityGroupId"     => (int) $y["specialityGroupId"],
                "specialityGroupName"   => (string) $y["specialityGroupName"],
            ];

            return $x;
        },[]);

        return $specialityGroups;
    }

}
