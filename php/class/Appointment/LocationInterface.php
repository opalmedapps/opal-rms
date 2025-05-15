<?php

declare(strict_types=1);

namespace Orms\Appointment;

use Orms\DataAccess\AppointmentAccess;
use Orms\DateTime;
use Orms\External\Backend\Export;
use Orms\Patient\Model\Patient;

class LocationInterface
{
    /**
     * Updates a patient's location for every appointment they have. If an appointment id was supplied, that appointment will be marked as the cause for the move in the db
     */
    public static function movePatientToLocation(Patient $patient, string $room, int $appointmentId = null, string $checkin_type): void
    {
        //get the list of appointments that the patient has today
        //only open appointments can be updated
        $appointments = AppointmentAccess::getOpenAppointments((new DateTime())->modify("midnight"),(new DateTime())->modify("tomorrow")->modify("-1 ms"),$patient->id);

        foreach($appointments as $app)
        {
            $intendedAppointment = ($app["appointmentId"] === $appointmentId);

            AppointmentAccess::moveAppointmentToLocation($app["appointmentId"], $room, $intendedAppointment);

            //also export the appointment to opal if the appointment originated in orms
            if ($checkin_type !== "APP" && $patient->opalStatus === 1){
                Export::exportPatientCheckin($patient,$app["sourceId"], $app["sourceSystem"]);
            }
            
        }
    }

    public static function removePatientLocationForAppointment(int $appointmentId): void
    {
        AppointmentAccess::removeAppointmentLocation($appointmentId);
    }

}
