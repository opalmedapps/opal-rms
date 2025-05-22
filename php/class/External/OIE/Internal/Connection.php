<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\OIE\Internal;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    # These are the last 2 endpoints which currently go to the OIE

    # Used when a patient checks in via kiosk, OIE forwards call to {serverAriaIE}/patient/hasPhoto and returns a boolean response
    public const API_ARIA_PHOTO                                 = "Patient/Photo";
    # Used when a new patient measurement is created by a clinical staff, pdf submitted to OIE channel (same channel as questionnaire pdf from backend)
    public const API_MEASUREMENT_PDF                            = "report/post";

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
