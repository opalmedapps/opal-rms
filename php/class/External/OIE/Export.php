<?php

declare(strict_types=1);

namespace Orms\External\OIE;

use DateTime;
use Orms\Config;
use Orms\Document\Measurement\Generator;
use Orms\External\OIE\Internal\Connection;
use Orms\Patient\Model\Patient;

class Export
{
    public static function exportPatientLocation(Patient $patient,string $sourceId, string $sourceSystem, string $room): void
    {
        try {
            Connection::getHttpClient()?->request("POST", Connection::API_PATIENT_LOCATION, [
                "json" => [
                    "room"          => $room,
                    "mrn"           => $patient->getActiveMrns()[0]->mrn,
                    "site"          => $patient->getActiveMrns()[0]->site,
                    "sourceId"      => $sourceId,
                    "sourceSystem"  => $sourceSystem,
                ],
                "timeout" => 10
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(), E_USER_WARNING);
        }
    }

    public static function exportAppointmentCompletion(string $sourceId, string $sourceSystem): void
    {
        try {
            Connection::getHttpClient()?->request("POST", Connection::API_APPOINTMENT_COMPLETION, [
                "json" => [
                    "sourceId"      => $sourceId,
                    "sourceSystem"  => $sourceSystem,
                    "status"        => "Completed"
                ]
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(), E_USER_WARNING);
        }
    }

    public static function exportRoomNotification(Patient $patient, string $appointmentId, string $appointmentSystem, string $roomNameEn, string $roomNameFr): void
    {
        if($patient->opalStatus !== 1) return;

        try {
            Connection::getHttpClient()?->request("POST", Connection::API_ROOM_NOTIFICATION, [
                "json" => [
                    "mrn"                   => $patient->getActiveMrns()[0]->mrn,
                    "site"                  => $patient->getActiveMrns()[0]->site,
                    "appointmentId"         => $appointmentId,
                    "appointmentSystem"     => $appointmentSystem,
                    "locationEN"            => $roomNameEn,
                    "locationFR"            => $roomNameFr,
                ]
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(), E_USER_WARNING);
        }
    }

    public static function exportMeasurementPdf(Patient $patient): void
    {
        if(Config::getApplicationSettings()->system->sendWeights !== true) return;

        //measurement document only suported by the RVH site for now
        $mrn = array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->mrn ?? throw new \Exception("No RVH mrn");
        $site = array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->site ?? throw new \Exception("No RVH mrn");

        Connection::getHttpClient()?->request("POST", Connection::API_MEASUREMENT_PDF, [
            "json" => [
                "mrn"             => $mrn,
                "site"            => $site,
                "reportContent"   => Generator::generatePdfString($patient),
                "docType"         => "ORMS Measurement",
                "documentDate"    => (new DateTime())->format("Y-m-d H:i:s"),
                "destination"     => "Streamline"
            ]
        ]);
    }
}
