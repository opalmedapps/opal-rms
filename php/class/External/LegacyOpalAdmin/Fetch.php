<?php

// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\LegacyOpalAdmin;

use Exception;
use Memcached;

use Orms\DateTime;
use Orms\External\LegacyOpalAdmin\Internal\Connection;
use Orms\Patient\Model\Patient;
use Orms\Config;
use Orms\Util\Encoding;

class Fetch
{
    private static ?Memcached $memcachedInstance = null;

    /**
     * Get or create a memcached object instance
     */
    private static function getMemcachedInstance(): Memcached {
        if (self::$memcachedInstance === null) {
            self::$memcachedInstance = new Memcached();
        }
        self::serverConnection(self::$memcachedInstance, 'memcached', 11211);
        return self::$memcachedInstance;
    }

    /**
     * Establish a connection to a server matching the request parameters, or return True if an existing one is found
     */
    private static function serverConnection(Memcached $memcached, string $host, int $port): bool {
        $servers = $memcached->getServerList();
        if (is_array($servers)) {
            foreach ($servers as $server) {
                if ($server['host'] === $host && $server['port'] === $port) {
                    return true;
                }
            }
        }
    
        $success = $memcached->addServer($host, $port);
        if (!$success) {
            error_log('Failed to add server: ' . $memcached->getResultMessage());
        }
    
        return $success;
    }
    
    /**
     * Get or set a LegacyOA cookie for system api calls
     */
    public static function getOrSetOACookie(): ?string
    {
        $memcached = self::getMemcachedInstance();
        $cookieKey = 'legacy_oa_cookie';
        $oaCookie = $memcached->get($cookieKey);
        if (!$oaCookie) {
            // No cookie in cache, log in and set it
            $oaCookie = self::loginToLegacyOpalAdmin();
            if (!$oaCookie) {
                throw new Exception('Could not perform system login to the LegacyOpalAdmin.');
            }
            $memcached->set($cookieKey, $oaCookie, 1440); // 24-minute expiry
        }

        return $oaCookie;
    }
    /**
     *  Credentialled system login to the legacy OpalAdmin API, retrieve and store Cookie for future API requests.
     */
    public static function loginToLegacyOpalAdmin(): ?string
    {
        $config = Config::getApplicationSettings()->environment;
        if($config === null) return null;

        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_SYSTEM_LOGIN, [
            // Login credentials for Legacy OpalAdmin system login
            'form_params' => [
                'username' => $config->opalAdminUsername,
                'password' => $config->opalAdminPassword,
            ]
        ]);
        $set_cookie = null;
        if ($response !== null && $response->getStatusCode() === 200) {

            $set_cookie = $response->getHeaderLine('Set-Cookie');
            if ($set_cookie) {
                // Store the cookie in Memcached for 24 minutes to match the default php maxlifetime in LegacyOA
                // https://stackoverflow.com/a/1516338
                $memcached = self::getMemcachedInstance();
                $memcached->set('legacy_oa_cookie', $set_cookie, 1440);
            }
        }
        
        return $set_cookie;
    }

    /**
     * Get the list of valid questionnaire purposes from OpalDB
     * @return list<array{
     *   purposeId: int,
     *   title: string,
     * }>
     */
    public static function getQuestionnairePurposes(): array
    {
        $cookie = self::getOrSetOACookie();
        $response = Connection::getHttpClient()?->request('GET', Connection::LEGACY_API_QUESTIONNAIRE_PURPOSE, [
            'headers' => [
                'Cookie' => $cookie,
            ]
        ])?->getBody()?->getContents() ?? '[]';

        $response = array_filter(json_decode($response, true), fn($x) => in_array($x['title_EN'], ['Clinical','Research']));

        return $response ? array_map(fn($x) => [
            'purposeId' => (int) $x['ID'], 
            'title' => (string) $x['title_EN']
        ], $response) : [];

    }

    /**
     *
     * @return list<array{
     *    questionnaireId: int,
     *    name: string
     * }>
     */
    public static function getListOfQuestionnaires(): array
    {
        $cookie = self::getOrSetOACookie();
        $response = Connection::getHttpClient()?->request('GET', Connection::LEGACY_API_QUESTIONNAIRE_PUBLISHED, [
            'headers' => [
                'Cookie' => $cookie,
            ]
        ])?->getBody()?->getContents() ?: '[]';

        $responseData = json_decode($response, true) ?? [];
        return array_map(fn($x) => [
            'questionnaireId' => (int) $x['ID'], 
            'name'            => (string) $x['name_EN']
        ], $responseData);
    }

    /**
     * Check if a patient exists in the opal system.
     */
    public static function isOpalPatient(Patient $patient): bool
    {
        $cookie = self::getOrSetOACookie();
        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_PATIENT_EXISTS, [
            'headers' => [
                'Cookie' => $cookie,
            ],
            'form_params' => [
                'mrn'  => $patient->getActiveMrns()[0]->mrn,
                'site' => $patient->getActiveMrns()[0]->site,
            ]
        ])?->getBody()?->getContents();

        return json_decode($response ?: '[]', true)['data'] ?? false;
    }

    /**
     * Get the list of patient mrns who have completed the questionnaires in questionnaireIds[]
     * @param int[] $questionnaireIds
     * @return list<array{
     *   mrn: string,
     *   site: string,
     *   completionDate: DateTime,
     *   lastUpdated: DateTime
     * }>
     */
    public static function getPatientsWhoCompletedQuestionnaires(array $questionnaireIds): array
    {
        $cookie = self::getOrSetOACookie();
        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_QUESTIONNAIRE_PATIENT_COMPLETED, [
            'headers' => [
                'Cookie' => $cookie,
            ],
            'form_params' => [
                'questionnaires' => $questionnaireIds,
            ],
        ])?->getBody()?->getContents();

        $responseData = json_decode($response ?: '[]', true);
        return $responseData ? array_map(fn($x) => [
            'mrn'            => (string) $x['mrn'],
            'site'           => (string) $x['site'],
            'completionDate' => DateTime::createFromFormat('Y-m-d H:i:s', $x['completionDate']) ?: throw new Exception('Invalid datetime'),
            'lastUpdated'    => DateTime::createFromFormat('Y-m-d H:i:s', $x['lastUpdated']) ?: throw new Exception('Invalid datetime'),
        ], $responseData) : [];

    }

    /**
     * Get the list of studies for which a patient has consented to participate in.
     * @return list<array{
     *   studyId: int,
     *   title: string
     * }>
     */
    public static function getStudiesForPatient(Patient $patient): array
    {
        $cookie = self::getOrSetOACookie();

        // Check for opal patient status
        $is_opal_patient = self::isOpalPatient($patient);

        if ($is_opal_patient) {
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_PATIENT_QUESTIONNAIRE_STUDY, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'  => $patient->getActiveMrns()[0]->mrn,
                    'site' => $patient->getActiveMrns()[0]->site,
                ]
            ])?->getBody()?->getContents();
            $response = !empty($response) ? $response : '[]';
            return array_map(fn($x) => [
                'studyId'         => (int) $x['studyId'],
                'title'           => (string) $x['title_EN']
            ], json_decode($response, true));    
        }
        return [];
    }


    /**
     * Retrieve a list of completed questionnaires and related studies for a given patient.
     * @return array<array-key, array{
     *      completionDate: string,
     *      completed: true,
     *      purposeId: int,
     *      purposeTitle: string,
     *      questionnaireId: int,
     *      questionnaireName: string,
     *      respondentId: int,
     *      respondentTitle: string,
     *      status: string,
     *      visualization: int,
     *      studyIdList: int[]
     * }>
     */
    public static function getListOfCompletedQuestionnairesForPatient(Patient $patient): array
    {
        $cookie = self::getOrSetOACookie();

        // Check for opal patient status
        $is_opal_patient = self::isOpalPatient($patient);

        if ($is_opal_patient) { 
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_PATIENT_QUESTIONNAIRE_COMPLETED, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'  => $patient->getActiveMrns()[0]->mrn,
                    'site' => $patient->getActiveMrns()[0]->site,
                ]
            ])?->getBody()?->getContents();
            $response = !empty($response) ? $response : '[]';
            return array_map(fn($x) => [
                'completionDate'        => (string) $x['completionDate'],
                'completed'             => true,
                'status'                => (string) $x['status'],
                'questionnaireId'       => (int) $x['questionnaireDBId'],
                'questionnaireName'     => (string) $x['name_EN'],
                'visualization'         => (int) $x['visualization'],
                'purposeId'             => (int) $x['purposeId'],
                'respondentId'          => (int) $x['respondentId'],
                'purposeTitle'          => (string) $x['purpose_EN'],
                'respondentTitle'       => (string) $x['respondent_EN'],
                'studyIdList'           => array_map('intval', $x['studies'] ?? [])
            ], json_decode($response, true));
        }
        return [];
    }

    /** Get a list of the most recently completed questionnaire per patient.
     * @param Patient[] $patients
     * @return array<int,null|array{
     *  questionnaireControlId: int,
     *  completionDate: DateTime,
     *  lastUpdated: DateTime
     * }>
     */
    public static function getLastCompletedQuestionnaireForPatients(array $patients): ?array
    {
        $cookie = self::getOrSetOACookie();

        $lastCompletedQuestionnaires = [];
        // Post params must be a form parameter list of mrn site pairs
        $postParams = [];
        foreach ($patients as $index => $p) {
            $activeMrn = $p->getActiveMrns()[0] ?? null;
            if ($activeMrn) {
                $postParams[$index . '[site]'] = $activeMrn->site;
                $postParams[$index . '[mrn]'] = $activeMrn->mrn;
            } else {
                // Handle the case where no active MRN is found
                error_log('No active MRN found for patient index: $index');
            }
        }
        $response = Connection::getHttpClient()?->request(
            'POST',
            Connection::LEGACY_API_PATIENT_QUESTIONNAIRE_LAST_COMPLETED,
            [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => $postParams,
            ]
        )?->getBody()?->getContents();
        $response = json_decode($response ?: '[]', true);
        // Transform the response
        foreach ($patients as $index => $p) {
            $r = ($response[$index] ?? null) ?: null; // Response for an individual patient may be false if the patient has no questionnaires
            if ($r !== null) {
                $r = [
                    'questionnaireControlId' => (int) $r['questionnaireControlId'],
                    'completionDate'         => DateTime::createFromFormat('Y-m-d H:i:s', $r['completionDate']) ?: throw new Exception('Invalid datetime'),
                    'lastUpdated'            => DateTime::createFromFormat('Y-m-d H:i:s', $r['lastUpdated']) ?: throw new Exception('Invalid datetime')
                ];
            }

            $lastCompletedQuestionnaires[$p->id] = $r;
        }

        return $lastCompletedQuestionnaires;

    }

    /**
     * Get patient responses to a chartable questionnaire (one that includes sliders)
     * @return list<array{
     *  questionId: int,
     *  questionTitle: string,
     *  questionLabel: string,
     *  answers: array<array-key,array{
     *       dateTimeAnswered: int,
     *       answer: int
     *  }>
     * }>
     */
    public static function getPatientAnswersForChartTypeQuestionnaire(Patient $patient, int $questionnaireId): array
    {
        $cookie = self::getOrSetOACookie();

        // Check for opal patient status
        $is_opal_patient = self::isOpalPatient($patient);

        if($is_opal_patient){
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_PATIENT_QUESTIONNAIRE_ANSWERS_CHART_TYPE, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'  => $patient->getActiveMrns()[0]->mrn,
                    'site' => $patient->getActiveMrns()[0]->site,
                    'questionnaireId'  => $questionnaireId
                ]
            ])?->getBody()?->getContents();
            $response = !empty($response) ? $response : '[]';
            return array_map(function($x) {
                //data should be sorted in asc datetime order
                usort($x['answers'], fn($a, $b) => $a['dateTimeAnswered'] <=> $b['dateTimeAnswered']);
                return [
                    'questionId'    => (int) $x['questionId'],
                    'questionTitle' => (string) $x['question_EN'],
                    'questionLabel' => (string) $x['display_EN'],
                    'answers'       => array_map(fn($y) => [
                                        'dateTimeAnswered' => (int) $y['dateTimeAnswered'],
                                        'answer'           => (int) $y['answer']
                                    ], $x['answers'])
                ];
            }, json_decode($response, true));
        }
        return [];
    }

    /**
     * Get patient responses to a non chartable questionnaire (one that does not include sliders)
     * @return list<array{
     *  questionnaireAnswerId: int,
     *  questionId: int,
     *  dateTimeAnswered: string,
     *  questionTitle: string,
     *  questionLabel: string,
     *  hasScale: bool,
     *  options: array<array-key,array{
     *      value: int,
     *      description: string
     *  }>,
     *  answers: string[]
     * }>
     */
    public static function getPatientAnswersForNonChartTypeQuestionnaire(Patient $patient, int $questionnaireId): array
    {
        $cookie = self::getOrSetOACookie();

        // Check for opal patient status
        $is_opal_patient = self::isOpalPatient($patient);

        if($is_opal_patient){
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_PATIENT_QUESTIONNAIRE_ANSWERS_NON_CHART_TYPE, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'  => $patient->getActiveMrns()[0]->mrn,
                    'site' => $patient->getActiveMrns()[0]->site,
                    'questionnaireId'  => $questionnaireId
                ]
            ])?->getBody()?->getContents();
            $response = !empty($response) ? $response : '[]';
            return array_map(fn($x) => [
                'questionnaireAnswerId' => (int) $x['answerQuestionnaireId'],
                'questionId'            => (int) $x['questionId'],
                'dateTimeAnswered'      => (string) $x['dateTimeAnswered'],
                'questionTitle'         => (string) $x['question_EN'],
                'questionLabel'         => (string) $x['display_EN'],
                'hasScale'              => ($x['legacyTypeId'] === '2'),
                'options'               => array_map(fn($y) => [
                                                'value'       => (int) $y['value'],
                                                'description' => (string) $y['description_EN']
                                            ], $x['options']),
                'answers'               => array_map(fn($y) => (string) $y['answer'], $x['answers'])
            ], json_decode($response, true));
        }
        return [];
    }

    /**
     * Check if an ICD diagnosis record exists in the legacy OpalAdmin
     */
    public static function getMasterSourceDiagnosisExists(string $diagSubcode): bool
    {
        $cookie = self::getOrSetOACookie();

        $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_DIAGNOSIS_EXISTS, [
            'headers' => [
                'Cookie' => $cookie,
            ],
            'form_params' => [
                'source'        => 'ORMS',
                'externalId'    => 'ICD-10',
                'code'          => $diagSubcode
            ]
        ])?->getBody()?->getContents();
        $responseData = json_decode($response ?: '[]', true);
        return !empty($decodedResponse['code']);
    }

    /**
     * Check if a patient has been assigned a particular ICD diagnosis in the legacy OpalAdmin
     *
     * @return list<array{
     *  isExternalSystem: 1,
     *  status: 'Active',
     *  createdDate: string,
     *  updatedDate: string,
     *  diagnosis: array{
     *      subcode: string,
     *      subcodeDescription: string
     *  }
     * }>
     */
    public static function getPatientDiagnosis(Patient $patient): array
    {
        $cookie = self::getOrSetOACookie();
        $is_opal_patient = self::isOpalPatient($patient);

        if($is_opal_patient){
            $response = Connection::getHttpClient()?->request('POST', Connection::LEGACY_API_GET_PATIENT_DIAGNOSIS, [
                'headers' => [
                    'Cookie' => $cookie,
                ],
                'form_params' => [
                    'mrn'       => $patient->getActiveMrns()[0]->mrn,
                    'site'      => $patient->getActiveMrns()[0]->site,
                    'source'    => 'ORMS',
                    'include'   => 0,
                    'startDate' => '1970-01-01',
                    'endDate'   => '2099-12-31'
                ]
            ])?->getBody()?->getContents();
            $responseData = json_decode($response ?: '[]', true);

            return array_map(fn($x) => [
                'isExternalSystem'  => 1,
                'status'            => 'Active',
                'createdDate'       => (string) $x['CreationDate'],
                'updatedDate'       => (string) $x['LastUpdated'],
                'diagnosis'         => [
                    'subcode'               => (string) $x['DiagnosisCode'],
                    'subcodeDescription'    => (string) $x['Description_EN']
                ]
            ], $responseData);
        }

        return [];
    }
    
}
