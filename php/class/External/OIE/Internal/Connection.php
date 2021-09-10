<?php

declare(strict_types=1);

namespace Orms\External\OIE\Internal;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const API_APPOINTMENT_COMPLETION                     = "Appointment/Status";
    public const API_APPOINTMENT_MRN                            = "Appointment";
    public const API_MEASUREMENT_PDF                            = "report/post";
    public const API_PATIENT_DIAGNOSIS                          = "Patient/Diagnosis";
    public const API_PATIENT_FETCH                              = "Patient";
    public const API_PATIENT_LOCATION                           = "Patient/Location";
    public const API_PATIENT_QUESTIONNAIRE_ANSWERS              = "Patient/Questionnaire/Answer";
    public const API_PATIENT_QUESTIONNAIRE_COMPLETED            = "Patient/Questionnaire/Completed";
    public const API_PATIENT_QUESTIONNAIRE_STUDY                = "Patient/Study";
    public const API_QUESTIONNAIRE_PATIENT_COMPLETED            = "Questionnaire/Patient";
    public const API_QUESTIONNAIRE_PUBLISHED                    = "Questionnaire/Published";
    public const API_QUESTIONNAIRE_PURPOSE                      = "Questionnaire/Purpose";
    public const API_ROOM_NOTIFICATION                          = "Patient/RoomNotification";

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
}
