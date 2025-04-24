<?php

// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\LegacyOpalAdmin\Internal;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const LEGACY_API_SYSTEM_LOGIN                          = 'user/system-login';
    public const LEGACY_API_PATIENT_EXISTS                        = 'patient/get/patient-exist';
    public const LEGACY_API_QUESTIONNAIRE_PUBLISHED               = 'questionnaire/get/published-questionnaires';
    public const LEGACY_API_QUESTIONNAIRE_PURPOSE                 = 'questionnaire/get/purposes';
    public const LEGACY_API_QUESTIONNAIRE_PATIENT_COMPLETED       = 'questionnaire/get/patients-completed-questionaires';
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_STUDY           = 'study/get/studies-patient-consented';
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_COMPLETED       = 'questionnaire/get/questionnaires-list-orms'; 
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_LAST_COMPLETED  = 'questionnaire/get/last-completed-questionnaire-list'; 
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_ANSWERS_CHART_TYPE     = 'questionnaire/get/chart-answers-patient'; 
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_ANSWERS_NON_CHART_TYPE     = 'questionnaire/get/non-chart-answers-patient';
    
    public const LEGACY_API_DIAGNOSIS_EXISTS                 = 'master-source/get/diagnosis-exists';
    public const LEGACY_API_INSERT_DIAGNOSIS                 = 'master-source/insert/diagnoses';

    public const LEGACY_API_GET_PATIENT_DIAGNOSIS                 = 'diagnosis/get/patient-diagnoses';
    public const LEGACY_API_INSERT_PATIENT_DIAGNOSIS              = 'diagnosis/insert/patient-diagnosis';
    public const LEGACY_API_DELETE_PATIENT_DIAGNOSIS              = 'diagnosis/delete/patient-diagnosis';

    public const LEGACY_API_ROOM_NOTIFICATION                          = "publisher/php/sendCallPatientNotification.php";
    public const LEGACY_API_APPOINTMENT_COMPLETION                     = "appointment/update/appointment-status";


    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->environment;

        return new Client([
            'base_uri'      => $config->legacyOpalAdminHostInternal,
            'verify'        => true
        ]);
    }
}
