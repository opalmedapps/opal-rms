<?php

// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\SourceSystem;

use DateTime;
use Orms\Config;
use Orms\External\SourceSystem\Connection;
use Orms\Patient\Model\Patient;

class Export
{
    // Notify SourceSystem of an appointment location update from ORMs
    public static function exportPatientLocation(string $sourceId, string $room): void
    {
        try {
            Connection::getHttpClient()?->request("POST", Connection::PATIENT_APPOINTMENT_LOCATION, [
                "json" => [
                    "appointmentId"      => $sourceId,
                    "location"               => $room,
                ],
                "timeout" => 10
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(), E_USER_WARNING);
        }
    }

}