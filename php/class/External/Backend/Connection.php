<?php

declare(strict_types=1);

namespace Orms\External\Backend;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const PATIENT_APPOINTMENT_CHECKIN = '/api/patients/legacy/appointment/checkin/';

    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->environment;

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
