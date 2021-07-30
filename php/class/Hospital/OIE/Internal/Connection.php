<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use Orms\Config;

class Connection
{
    static function getHttpClient(): ?Client
    {
        $config = Config::getApplicationSettings()->oie;
        if($config === NULL) return NULL;

        return new Client([
            "base_uri"      => $config->oieUrl,
            "verify"        => FALSE, //this should be changed at some point...
            // "http_errors"   => FALSE,
            "auth"          => [$config->username,$config->password]
        ]);
    }

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
                "username" => Config::getApplicationSettings()->opal?->opalAdminUsername,
                "password" => Config::getApplicationSettings()->opal?->opalAdminPassword
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
