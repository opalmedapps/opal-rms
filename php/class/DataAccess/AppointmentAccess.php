<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Orms\DataAccess\Database;

class AppointmentAccess
{
    /**
     *
     * @return list<array{
     *  AppointmentCode: string
     * }>
     */
    public static function getAppointmentCodes(int $specialityGroupId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                COALESCE(DisplayName,AppointmentCode) AS AppointmentCode
            FROM
                AppointmentCode
            WHERE
                SpecialityGroupId = ?
            ORDER BY
                AppointmentCode
        ");
        $query->execute([$specialityGroupId]);

        return array_map(fn($x) => [
            "AppointmentCode" => $x["AppointmentCode"]
        ],$query->fetchAll());
    }

    public static function completeCheckedInAppointments(): void
    {
        $dbh = Database::getOrmsConnection();

        //add all Patientlocation rows to the MH table
        $dbh->query("
            INSERT INTO PatientLocationMH (
                PatientLocationSerNum,
                PatientLocationRevCount,
                AppointmentSerNum,
                CheckinVenueName,
                ArrivalDateTime,
                DichargeThisLocationDateTime,
                IntendedAppointmentFlag
            )
            SELECT
                PatientLocationSerNum,
                PatientLocationRevCount,
                AppointmentSerNum,
                CheckinVenueName,
                ArrivalDateTime,
                NOW(),
                IntendedAppointmentFlag
            FROM
                PatientLocation
        ");

        //complete the appointment attached to the PatientLocation row
        $dbh->query("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN PatientLocation PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
            SET
                MV.Status = 'Completed'
        ");

        //clear the PatientLocation table
        $dbh->query("DELETE FROM PatientLocation WHERE 1");
    }
}
