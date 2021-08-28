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
     *   specialityGroupId: int,
     *   specialityCode: string,
     *   specialityName: string
     * }>
     */
    public static function getSpecialityGroups(): array
    {
        return HospitalAccess::getSpecialityGroups();
    }

    public static function getSpecialityGroupId(string $specialityCode): ?int
    {
        $groups = HospitalAccess::getSpecialityGroups();
        return array_values(array_filter($groups,fn($x) => $x["specialityCode"] === $specialityCode))[0]["specialityGroupId"] ?? null;
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

    /**
     *
     * @return list<array{
     *  name: string,
     *  type: string,
     *  screenDisplayName: string,
     *  venueEN: string,
     *  venueFR: string
     * }>
     */
    public static function getRooms(int $clinicHubId): array
    {
        return HospitalAccess::getRooms($clinicHubId);
    }

    /**
     *
     * @param string[] $rooms
     * @return list<array{
     *  name: string,
     *  arrival: string,
     *  patientId: string,
     *  patientName: string,
     * }>
     */
    public static function getOccupantsForExamRooms(array $rooms): array
    {
        return HospitalAccess::getOccupantsForExamRooms($rooms);
    }

}
