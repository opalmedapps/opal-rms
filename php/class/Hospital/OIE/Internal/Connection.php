<?php

declare(strict_types=1);

namespace Orms\Hospital\OIE\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use Orms\Config;

class Connection
{
    public const API_APPOINTMENT_COMPLETION             = "Appointment/Status";
    public const API_MEASUREMENT_PDF                    = "report/post";
    public const API_PATIENT_QUESTIONNAIRE_COMPLETED    = "questionnaire/get/questionnaires-list-orms";
    public const API_PATIENT_QUESTIONNAIRE_LAST         = "opalAdmin/questionnaire/get/last-completed-quesitonnaire";
    public const API_PATIENT_QUESTIONNAIRE_PUBLISHED    = "questionnaire/get/published-questionnaires";
    public const API_PATIENT_DIAGNOSIS                  = "Patient/Diagnosis";
    public const API_PATIENT_FETCH                      = "patient/get";
    public const API_PATIENT_LOCATION                   = "Patient/Location";
    public const API_QUESTIONNAIRE_ANSWERS_CHART        = "opalAdmin/questionnaire/get/chart-answers-patient";
    public const API_QUESTIONNAIRE_ANSWERS_NON_CHART    = "opalAdmin/questionnaire/get/non-chart-answers-patient";
    public const API_QUESTIONNAIRE_PATIENT_COMPLETED    = "opalAdmin/questionnaire/get/patients-completed-questionaires";
    public const API_QUESTIONNAIRE_PURPOSE              = "opalAdmin/questionnaire/get/purposes";
    public const API_QUESTIONNAIRE_STUDY                = "/opalAdmin/study/get/studies-patient-consented";
    public const API_ROOM_NOTIFICATION                  = "Patient/RoomNotification";

    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->oie;
        if($config === null) return null;

        return new Client([
            "base_uri"      => $config->oieUrl,
            "verify"        => false, //this should be changed at some point...
            // "http_errors"   => FALSE,
            "auth"          => [$config->username,$config->password]
        ]);
    }

    public static function getOpalHttpClient(): ?Client
    {
        $url = Config::getApplicationSettings()->opal?->opalAdminUrl;
        if($url === null) return null;

        $url = $url . (mb_substr($url, -1) === "/" ? "" : "/"); //add trailing slash if there is none

        //make an initial login request to get an auth cookie
        $cookies = new CookieJar();
        (new Client([
            "base_uri"      => $url,
            "verify"        => false,
        ]))->request("POST", "user/system-login", [
            "form_params" => [
                "username" => Config::getApplicationSettings()->opal?->opalAdminUsername,
                "password" => Config::getApplicationSettings()->opal?->opalAdminPassword
            ],
            "cookies" => $cookies
        ]);

        $authCookie = new CookieJar(false, [$cookies->getCookieByName("PHPSESSID")]);

        //create an client that can authenticate with OpalAdmin
        return new Client([
            "base_uri"      => $url,
            "verify"        => false, //this should be changed at some point...
            // "http_errors"   => FALSE
            "cookies"       => $authCookie
        ]);
    }
}
