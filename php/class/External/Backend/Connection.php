<?php

// SPDX-FileCopyrightText: Copyright (C) 2024 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\Backend;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const PATIENT_APPOINTMENT_CHECKIN = '/api/patients/legacy/appointment/checkin/';
    # TODO: Remove this hardcoded mapping after the backend implements a new appointment endpoint
    public const SOURCE_SYSTEM_MAPPING = [
        'ARIA' => 1,
        'MEDIVISIT' => 2,
        'ERDV' => 5,
        'ORMS' => 6,
    ];

    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->system;

        return new Client([
            'base_uri'      => $config->newOpalAdminHostInternal,
            'verify'        => true,
            'headers'  => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Token ' . $config->newOpalAdminToken,
            ]
        ]);
    }
}
