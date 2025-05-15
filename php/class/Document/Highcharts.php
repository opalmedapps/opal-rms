<?php

declare(strict_types=1);

namespace Orms\Document;

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
