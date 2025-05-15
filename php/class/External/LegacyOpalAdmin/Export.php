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
    
        // TODO: OA accepts only one description field, but we can send both EN and FR
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
        // TODO: Process response code
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
        // TODO: Process response code
    }

    /**
     * Unassign an ICD diagnosis from an opal patient
     */
    public static function deletePatientDiagnosis(Patient $patient, int $diagId, string $diagSubcode, \DateTime $creationDate, string $descEn, string $descFr, string $status){
        $cookie = Fetch::getOrSetOACookie();
    
        // TODO: OA accepts only one description field, but we can send both EN and FR
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
        // $responseData = json_decode($response?->getBody()?->getContents() ?: '[]', true)['data'] ?? false;
        // TODO: Process response code
    }
}
