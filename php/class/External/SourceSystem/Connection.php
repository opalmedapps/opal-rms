<?php

declare(strict_types=1);

namespace Orms\External\SourceSystem;

use GuzzleHttp\Client;

use Orms\Config;

class Connection
{
    public const PATIENT_APPOINTMENT_LOCATION = '/patient/location';

    public static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->environment;

        return new Client([
            'base_uri'      => $config->sourceSystemHost,
            'verify'        => true,
            'headers'  => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }
}
