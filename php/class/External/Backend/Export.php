<?php

// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\Backend;

use DateTime;
use Orms\Config;
use Orms\External\Backend\Connection;
use Orms\Patient\Model\Patient;

class Export
{
    // Notify Backend of an appointment completion from ORMs
    public static function exportPatientCheckin(Patient $patient,string $sourceId, string $sourceSystem): void
    {
        try {
            // Convert string sourceSystem to OpalDB.SourceDatabase.SourceDatabaseSerNum
            $sourceDatabase = Connection::SOURCE_SYSTEM_MAPPING[strtoupper($sourceSystem)];
            if ($sourceDatabase === null) {
                trigger_error("Unknown source system: $sourceSystem", E_USER_WARNING);
                return; // Early exit if mapping is not found
            }

            Connection::getHttpClient()?->request("POST", Connection::PATIENT_APPOINTMENT_CHECKIN, [
                "json" => [
                    "source_system_id"      => $sourceId,
                    "source_database"       => $sourceDatabase,
                    "checkin"               => 1,
                ],
                "timeout" => 10
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(), E_USER_WARNING);
        }
    }

}