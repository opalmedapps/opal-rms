<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use Orms\Config;

class Highcharts
{
    /**
     * Returns a base64 encoded string containing an image created by a highcharts export server using an input chart array
     * @param mixed[] $chart
     * @throws GuzzleException
     */
    public static function generateImageDataFromChart(array $chart): string
    {
        $url = Config::getApplicationSettings()->environment->highchartsUrl;

        if($url === null) return "";

        $client = new Client();
        $request = $client->request("POST", $url, [
            "json" => [
                "options" => json_encode($chart),
                "type"    => "png",
                "b64"     => true
            ]
        ])->getBody()->getContents();

        return $request;
    }
}
