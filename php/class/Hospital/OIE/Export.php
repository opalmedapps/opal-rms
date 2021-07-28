<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE;

use Orms\Config;
use Orms\Patient\Model\Patient;
use Orms\Document\Measurement\Generator;
use Orms\Hospital\OIE\Internal\Connection;

class Export
{
    static function exportPatientLocation(string $sourceId,string $sourceSystem,string $room): void
    {
        Connection::getHttpClient()?->request("POST","Patient/Location",[
            "json" => [
                "room"          => $room,
                "sourceId"      => $sourceId,
                "sourceSystem"  => $sourceSystem
            ]
        ]);
    }

    static function exportAppointmentCompletion(string $sourceId,string $sourceSystem): void
    {
        Connection::getHttpClient()?->request("POST","Appointment/Status",[
            "json" => [
                "sourceId"      => $sourceId,
                "sourceSystem"  => $sourceSystem,
                "status"        => "Completed"
            ]
        ]);
    }

    static function exportPushNotification(Patient $patient,string $appointmentId,string $roomNameEn,string $roomNameFr): void
    {
        if($patient->opalStatus !== 1) return;

        try {
            Connection::getOpalHttpClient()?->request("GET","publisher/php/sendCallPatientNotification.php",[
                "query" => [
                    "mrn"                   => $patient->getActiveMrns()[0]->mrn,
                    "site"                  => $patient->getActiveMrns()[0]->site,
                    "appointment_ariaser"   => $appointmentId,
                    "room_EN"               => $roomNameEn,
                    "room_FR"               => $roomNameFr,
                ]
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(),E_USER_WARNING);
        }
    }

    static function exportMeasurementPdf(Patient $patient): void
    {
        if(Config::getApplicationSettings()->system->sendWeights !== TRUE) return;

        Connection::getHttpClient()?->request("POST","exportWeightPdf",[
            "json" => [
                "mrn"   => $patient->getActiveMrns()[0]->mrn,
                "site"  => $patient->getActiveMrns()[0]->site,
                "pdf"   => Generator::generatePdfString($patient)
            ]
        ]);
    }

    static function exportPatientDiagnosis(Patient $patient,int $diagId,string $diagSubcode,\DateTime $creationDate,string $descEn,string $descFr,string $status): void
    {
        Connection::getHttpClient()?->request("POST","/Patient/Diagnosis",[
            "json" => [
                "mrn"           => $patient->getActiveMrns()[0]->mrn,
                "site"          => $patient->getActiveMrns()[0]->site,
                "source"        => "ORMS",
                "rowId"         => $diagId,
                "externalId"    => "ICD-10",
                "code"          => $diagSubcode,
                "creationDate"  => $creationDate->format("Y-m-d H:i:s"),
                "descriptionEn" => $descEn,
                "descriptionFr" => $descFr,
                "status"        => $status
            ]
        ]);
    }
}
