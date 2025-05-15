<?php

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
            Connection::getHttpClient()?->request("POST", Connection::PATIENT_APPOINTMENT_CHECKIN, [
                "json" => [
                    "source_system_id"      => $sourceId,
                    "sourceSystem"          => $sourceSystem,
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