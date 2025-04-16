<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Appointment;

use Orms\DataAccess\AppointmentAccess;
use Orms\DateTime;
use Orms\External\Backend\Export as BackendExport;
use Orms\External\SourceSystem\Export as SourceSystemExport;
use Orms\Patient\Model\Patient;

use Orms\Config;

class LocationInterface
{
    /**
     * Updates a patient's location for every appointment they have. If an appointment id was supplied, that appointment will be marked as the cause for the move in the db
     */
    public static function movePatientToLocation(Patient $patient, string $room, int $appointmentId = null, string $checkinType): void
    {
        // load environment configurations
        $config = Config::getApplicationSettings()->environment;

        //get the list of appointments that the patient has today
        //only open appointments can be updated
        $appointments = AppointmentAccess::getOpenAppointments((new DateTime())->modify("midnight"),(new DateTime())->modify("tomorrow")->modify("-1 ms"),$patient->id);

        foreach($appointments as $app)
        {
            $intendedAppointment = ($app["appointmentId"] === $appointmentId);

            AppointmentAccess::moveAppointmentToLocation($app["appointmentId"], $room, $intendedAppointment);

            //also export the appointment to opal if the appointment originated in orms
            if ($checkinType !== "APP" && $patient->opalStatus === 1){
                BackendExport::exportPatientCheckin($patient,$app["sourceId"], $app["sourceSystem"]);
            }

            // export appointment location to source system if enabled && appointment came from Aria
            if ($checkinType !== "APP" && $app["sourceSystem"] === "Aria" && $config->sourceSystemSupportsCheckin){
                SourceSystemExport::exportPatientLocation($app["sourceId"], $room);
            }
            
        }
    }

    public static function removePatientLocationForAppointment(int $appointmentId): void
    {
        AppointmentAccess::removeAppointmentLocation($appointmentId);
    }

}
