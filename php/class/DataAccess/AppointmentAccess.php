<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Orms\DataAccess\Database;

class AppointmentAccess
{
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
