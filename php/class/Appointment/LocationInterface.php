<?php

declare(strict_types=1);

namespace Orms\Appointment;

use DateTime;
use Orms\DataAccess\AppointmentAccess;
use Orms\External\OIE\Export;
use Orms\Patient\Model\Patient;

class LocationInterface
{
    /**
     * Updates a patient's location for every appointment they have. If an appointment id was supplied, that appointment will be marked as the cause for the move in the db
     */
    public static function movePatientToLocation(Patient $patient, string $room, int $appointmentId = null): void
    {
        //get the list of appointments that the patient has today
        //only open appointments can be updated
        $appointments = AppointmentAccess::getOpenAppointmentsForPatient($patient->id,(new DateTime())->modify("midnight"),(new DateTime())->modify("tomorrow"));

        foreach($appointments as $app)
        {
            $intendedAppointment = ($app["appointmentId"] === $appointmentId);

            AppointmentAccess::moveAppointmentToLocation($app["appointmentId"], $room, $intendedAppointment);

            //also export the appointment to other systems
            Export::exportPatientLocation($app["sourceId"], $app["sourceSystem"], $room);
        }
    }

    public static function removePatientLocationForAppointment(int $appointmentId): void
    {
        AppointmentAccess::removeAppointmentLocation($appointmentId);
    }

}
