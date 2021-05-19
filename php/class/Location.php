<?php declare(strict_types = 1);

namespace Orms;

use PDOException;

use Orms\Database;
use Orms\Patient\Patient;
use Orms\Aria;
use Orms\Hospital\OIE\Export;

class Location
{
    /**
     * Updates a patient's location for every appointment they have. If an appointment id was supplied, that appointment will be marked as the cause for the move in the db
     */
    static function movePatientToLocation(Patient $patient,string $room,int $appointmentId = NULL): void
    {
        //get the list of appointments that the patient has today
        //only open appointments can be updated
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                MV.AppointmentSerNum,
                MV.AppointId,
                MV.AppointSys
            FROM
                Patient P
                INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
                    AND MV.ScheduledDate = CURDATE()
                    AND MV.Status = 'Open'
            WHERE
                P.PatientSerNum = :patId
            ORDER BY
                MV.ScheduledDateTime
        ");
        $query->execute([":patId" => $patient->id]);

        $appointments = $query->fetchAll();

        foreach($appointments as $app)
        {
            $intendedAppointment = (((int) $app["AppointmentSerNum"]) === $appointmentId);

            self::_movePatientToLocationForAppointment((int) $app["AppointmentSerNum"],$room,$intendedAppointment);

            //also export the appointment to other systems
            if($app["AppointSys"] === "Aria") Aria::exportMoveToAria($app["AppointId"],$room);
            Export::exportPatientLocation($app["AppointId"],$app["AppointSys"],$room);
        }
    }

    /**
     * Returns the revCount of the PatentLocation row that was removed, or 0 if there was none
     * @throws PDOException
     */
    static function removePatientLocationForAppointment(int $appointmentId): int
    {
        $dbh = Database::getOrmsConnection();

        //check if the patient is already checked in for this appointment
        $queryExistingCheckIn = $dbh->prepare("
            SELECT DISTINCT
                PL.PatientLocationSerNum,
                PL.PatientLocationRevCount,
                PL.CheckinVenueName,
                PL.ArrivalDateTime,
                PL.IntendedAppointmentFlag
            FROM
                PatientLocation PL
            WHERE
                PL.AppointmentSerNum = ?
        ");
        $queryExistingCheckIn->execute([$appointmentId]);

        $currentLocation = $queryExistingCheckIn->fetchAll()[0] ?? NULL;

        if($currentLocation === NULL) return 0;

        //move the old location to the MH table and then delete it
        $queryInsertLocationMH = $dbh->prepare("
            INSERT INTO PatientLocationMH
            SET
                PatientLocationSerNum    = :plId,
                PatientLocationRevCount  = :revCount,
                AppointmentSerNum        = :appId,
                CheckinVenueName         = :room,
                ArrivalDateTime          = :arrival,
                IntendedAppointmentFlag  = :intended
        ");
        $queryInsertLocationMH->execute([
            "plId"       => $currentLocation["PatientLocationSerNum"],
            ":revCount"  => $currentLocation["PatientLocationRevCount"],
            ":appId"     => $appointmentId,
            ":room"      => $currentLocation["CheckinVenueName"],
            ":arrival"   => $currentLocation["ArrivalDateTime"],
            ":intended"  => $currentLocation["IntendedAppointmentFlag"]
        ]);

        $queryDeleteLocation = $dbh->prepare("
            DELETE FROM PatientLocation
            WHERE
                PatientLocationSerNum = ?
        ");
        $queryDeleteLocation->execute([$currentLocation["PatientLocationSerNum"]]);

        return (int) $currentLocation["PatientLocationRevCount"];
    }

    private static function _movePatientToLocationForAppointment(int $appointmentId,string $room,bool $intendedAppointment): void
    {
        //remove any current PatientLocation rows for this appointment
        $currentRevCount = self::removePatientLocationForAppointment($appointmentId) +1;

        //insert the new location
        Database::getOrmsConnection()->prepare("
            INSERT INTO PatientLocation
            SET
                PatientLocationRevCount = :revCount,
                AppointmentSerNum       = :appId,
                CheckinVenueName        = :room,
                ArrivalDateTime         = NOW(),
                IntendedAppointmentFlag = :intended
        ")->execute([
            ":revCount"  => $currentRevCount,
            ":appId"     => $appointmentId,
            ":room"      => $room,
            ":intended"  => (int) $intendedAppointment
        ]);
    }

}

?>
