<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE;

use Orms\Config;
use Orms\Patient;
use Orms\Patient\Mrn;
use Orms\Document\Measurement\Generator;
use Orms\Hospital\OIE\Internal\Connection;

class Export
{
    static function exportPatientLocation(string $sourceId,string $sourceSystem,string $room): void
    {
        Connection::getHttpClient()?->request("POST","",[
            "json" => [
                "room"          => $room,
                "sourceId"      => $sourceId,
                "sourceSystem"  => $sourceSystem

            ]
        ]);
    }

    static function exportAppointmentCompletion(string $sourceId,string $sourceSystem): void
    {
        Connection::getHttpClient()?->request("POST","",[
            "json" => [
                "sourceId"      => $sourceId,
                "sourceSystem"  => $sourceSystem
            ]
        ]);
    }

    static function exportPushNotification(Patient $patient,string $appointmentId,string $roomNameEn,string $roomNameFr): void
    {
        if($patient->opalPatient !== 1) return;

        try {
            Connection::getOpalHttpClient()?->request("GET","publisher/php/sendCallPatientNotification.php",[
                "query" => [
                    "PatientId"             => $patient->mrns[0]->mrn,
                    //"site" => $mrn->site //the site is missing from the api...
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
            "json"  => [
                "mrn"   => $patient->mrns[0]->mrn,
                "site"  => $patient->mrns[0]->site,
                "pdf"   => Generator::generatePdfString($patient)
            ]
        ]);
    }

    static function exportPatientDiagnosis(Mrn $patient,int $diagId,string $diagSubcode,\DateTime $creationDate,string $descEn,string $descFr): void
    {
        Connection::getOpalHttpClient()?->request("POST","diagnosis/insert/patient-diagnosis",[
            "form_params" => [
                "mrn"           => $patient->mrn,
                "site"          => $patient->site,
                "source"        => "ORMS",
                "rowId"         => $diagId,
                "code"          => $diagSubcode,
                "creationDate"  => $creationDate->format("Y-m-d H:i:s"),
                "descriptionEn" => $descEn,
                "descriptionFr" => $descFr,
            ]
        ]);
    }

    static function exportPatientDiagnosisDeletion(Mrn $patient,int $diagId): void
    {
        Connection::getOpalHttpClient()?->request("POST","diagnosis/delete/patient-diagnosis",[
            "form_params" => [
                "mrn"           => $patient->mrn,
                "site"          => $patient->site,
                "source"        => "ORMS",
                "rowId"         => $diagId
            ]
        ]);
    }

}

?>
