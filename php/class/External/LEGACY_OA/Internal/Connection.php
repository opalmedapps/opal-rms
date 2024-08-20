<?php

declare(strict_types=1);

namespace Orms\External\LEGACY_OA\Internal;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const LEGACY_API_SYSTEM_LOGIN                        = "user/system-login";
    public const LEGACY_API_PATIENT_EXISTS                      = "patient/get/patient-exist";
    public const LEGACY_API_QUESTIONNAIRE_PUBLISHED             = "questionnaire/get/published-questionnaires";
    public const LEGACY_API_QUESTIONNAIRE_PURPOSE               = "questionnaire/get/purposes";
    public const LEGACY_API_QUESTIONNAIRE_PATIENT_COMPLETED     = "questionnaire/get/patients-completed-questionaires";
    public const LEGACY_API_PATIENT_QUESTIONNAIRE_STUDY         = "study/get/studies-patient-consented";

    public const API_PATIENT_QUESTIONNAIRE_ANSWERS              = "Patient/Questionnaire/Answer"; // opalAdmin/questionnaire/get/chart-answers-patient OR opalAdmin/questionnaire/get/non-chart-answers-patient
    public const API_PATIENT_QUESTIONNAIRE_COMPLETED            = "Patient/Questionnaire/Completed"; // opalAdmin/questionnaire/get/questionnaires-list-orms && (with filter logic) opalAdmin/questionnaire/get/last-completed-questionnaire-list

    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->environment;
        if($config === null) return null;

        return new Client([
            "base_uri"      => $config->legacyApiUrl,
            "verify"        => true
        ]);
    }
}
