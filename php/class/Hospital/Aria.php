<?php declare(strict_types = 1);

namespace Orms\Hospital;

use GuzzleHttp\Client;

use Orms\Config;

class Aria
{
    static function exportMoveToAria(string $sourceId,string $room): void
    {
        $ariaURL = Config::getApplicationSettings()->aria?->checkInUrl;
        if($ariaURL === NULL) return;

        $client = new Client();

        try {
            $client->request("GET",$ariaURL,[
                "query" => [
                    "appointmentId" => $sourceId,
                    "location"      => $room
                ]
            ]);
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage() ."\n". $e->getTraceAsString(),E_USER_WARNING);
        }
    }

}
