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
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                LTRIM(RTRIM(AriaVenueId)) AS Name,
                'ExamRoom' AS Type,
                ScreenDisplayName,
                VenueEN,
                VenueFR
            FROM
                ExamRoom
            WHERE
                ClinicHubId = :id
            UNION
            SELECT
                LTRIM(RTRIM(AriaVenueId)) AS Name,
                'IntermediateVenue' AS Type,
                ScreenDisplayName,
                VenueEN,
                VenueFR
            FROM
                IntermediateVenue
            WHERE
                ClinicHubId = :id
        ");
        $query->execute([":id" => $clinicHubId]);

        $rooms = $query->fetchAll();
        usort($rooms,fn($a,$b) => [$a["Type"],$a["Name"]] <=> [$b["Type"],$b["Name"]]);

        return array_map(fn($x) => [
            "name"              => $x["Name"],
            "type"              => $x["Type"],
            "screenDisplayName" => $x["ScreenDisplayName"],
            "venueEN"           => $x["VenueEN"],
            "venueFR"           => $x["VenueFR"],
        ],$rooms);
    }

}
