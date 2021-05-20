<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use Orms\Config;

class Connection
{
    static function getHttpClient(): ?Client
    {
        if(Config::getApplicationSettings()->environment->oieUrl === NULL) return NULL;

        return new Client([
            "base_uri"      => Config::getApplicationSettings()->environment->oieUrl,
            "verify"        => FALSE, //this should be changed at some point...
            // "http_errors"   => FALSE
        ]);
    }

    // TODO: load username/password from config file
    static function getOpalHttpClient(): ?Client
    {
        $url = Config::getApplicationSettings()->opal?->opalAdminUrl;
        if($url === NULL) return NULL;

        $url = $url . (substr($url,-1) === "/" ? "" : "/"); //add trailing slash if there is none

        //make an initial login request to get an auth cookie
        $cookies = new CookieJar();
        (new Client([
            "base_uri"      => $url,
            "verify"        => FALSE,
        ]))->request("POST","user/system-login",[
            "form_params" => [
                "username" => "ORMS",
                "password" => "Password1@"
            ],
            "cookies" => $cookies
        ]);

        $authCookie = new CookieJar(FALSE,[$cookies->getCookieByName("PHPSESSID")]);

        //create an client that can authenticate with OpalAdmin
        return new Client([
            "base_uri"      => $url,
            "verify"        => FALSE, //this should be changed at some point...
            // "http_errors"   => FALSE
            "cookies"       => $authCookie
        ]);
    }
}
