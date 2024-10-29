<?php

declare(strict_types=1);

namespace Orms\External\LegacyOpalAdmin;

use DateTime;
use Orms\Config;
use Orms\Document\Measurement\Generator;
use Orms\External\LegacyOpalAdmin\Internal\Connection;
use Orms\Patient\Model\Patient;
use Orms\External\LegacyOpalAdmin\Fetch;

class Export
{
    /**
     * Insert a new ICD diagnosis record to the masterSource list in the legacy OpalAdmin
     */
    public static function insertMasterSourceDiagnosis(string $diagSubcode, \DateTime $creationDate, string $descEn, string $descFr){
        $cookie = Fetch::getOrSetOACookie();
    
        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_INSERT_DIAGNOSIS, [
            'headers' => [
                'Cookie' => $cookie,
            ],
            'form_params' => [
                'item' => [
                    'source'        => 'ORMS',
                    'externalId'    => 'ICD-10',
                    'code'          => $diagSubcode,
                    'creationDate'  => $creationDate->format('Y-m-d H:i:s'),
                    'description' => $descEn
                ]
            ]
        ])?->getBody()?->getContents();
    }

    /**
     * Insert an opal patient ICD diagnosis to the legacy OpalAdmin
     */
    public static function insertPatientDiagnosis(Patient $patient, int $diagId, string $diagSubcode, \DateTime $creationDate, string $descEn, string $descFr, string $status): void
    {
        $cookie = Fetch::getOrSetOACookie();
        
        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_INSERT_PATIENT_DIAGNOSIS, [
            'headers' => [
                'Cookie' => $cookie,
            ],
            'form_params' => [
                'mrn'           => $patient->getActiveMrns()[0]->mrn,
                'site'          => $patient->getActiveMrns()[0]->site,
                'source'        => 'ORMS',
                'rowId'         => $diagId,
                'externalId'    => 'ICD-10',
                'code'          => $diagSubcode,
                'creationDate'  => $creationDate->format('Y-m-d H:i:s'),
                'descriptionEn' => $descEn,
                'descriptionFr' => $descFr,
                'status'        => $status
            ]
        ])?->getBody()?->getContents();
    }

    /**
     * Unassign an ICD diagnosis from an opal patient
     */
    public static function deletePatientDiagnosis(Patient $patient, int $diagId, string $diagSubcode, \DateTime $creationDate, string $descEn, string $descFr, string $status){
        $cookie = Fetch::getOrSetOACookie();
    
        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_DELETE_PATIENT_DIAGNOSIS, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'           => $patient->getActiveMrns()[0]->mrn,
                    'site'          => $patient->getActiveMrns()[0]->site,
                    'source'        => 'ORMS',
                    'rowId'         => $diagId,
                    'externalId'    => 'ICD-10',
                    'code'          => $diagSubcode,
                    'creationDate'  => $creationDate->format('Y-m-d H:i:s'),
                    'descriptionEn' => $descEn,
                    'descriptionFr' => $descFr,
                    'status'        => $status
                ]
            ]
        );
        $responseCode = $response->getStatusCode();
    }

    public static function exportAppointmentCompletion(string $sourceId, string $sourceSystem): void
    {
        $cookie = Fetch::getOrSetOACookie();
        try {
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_APPOINTMENT_COMPLETION, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'sourceId'      => $sourceId,
                    'sourceSystem'  => $sourceSystem,
                    'status'        => 'Completed'
                ]
            ]);
            // $responseCode = $response->getStatusCode();
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() .'\n'. $e->getTraceAsString(), E_USER_WARNING);
        }
    }

    public static function exportRoomNotification(Patient $patient, string $appointmentId, string $appointmentSystem, string $roomNameEn, string $roomNameFr): void
    {
        if($patient->opalStatus !== 1) return;
        $cookie = Fetch::getOrSetOACookie();
        try {
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_ROOM_NOTIFICATION, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'                   => $patient->getActiveMrns()[0]->mrn,
                    'site'                  => $patient->getActiveMrns()[0]->site,
                    'appointment_ariaser'   => $appointmentId,
                    'appointmentSystem'     => $appointmentSystem,
                    'room_EN'            => $roomNameEn,
                    'room_FR'            => $roomNameFr,
                ]
            ]);
            // $responseCode = $response->getStatusCode();
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() .'\n'. $e->getTraceAsString(), E_USER_WARNING);
        }
    }
}
