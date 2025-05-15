<?php

declare(strict_types=1);

namespace Orms;

use Exception;
use GuzzleHttp\Client;
use Memcached;
use Orms\System\Logger;
use Orms\Config;
use Psr\Http\Message\ResponseInterface;

class Authentication
{
    public static function login(string $username, string $password): ResponseInterface
    {
        $authUrl = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/auth/orms/login/';
        //check if the credentials are valid in the AD
        $client = new Client();
        $response = $client->request(
            "POST",
            $authUrl,
            [
                "form_params" => [
                    "username"           => $username,
                    "password"           => $password,
                ],
                "http_errors" => false,
            ],
        );
        $statusCode = $response->getStatusCode(); 
        $error = ($statusCode != 200) ? "Authentication failure" : "";
        Logger::logLoginEvent($username,$username,$statusCode,$error);

        return $response;
    }

    /**
     *
     * @return array{
     *      name: string,
     *      key: string
     * }
     */
    public static function createUserSession(string $username): array
    {
        //store the user session in the memcache
        $memcache = new Memcached(); // connect to memcached on localhost port 11211
        $memcache->addServer("memcached", 11211) ?: throw new Exception("Failed to connect to memcached server");

        //generate cookie uniq session id
        $key = md5(uniqid((string) rand(), true) .$_SERVER["REMOTE_ADDR"]. time());

        //$exists = $memcache->get($key);

        //contruct session value to be stored in memcached for the cookie session id.
        $value = "UserName=$username\r\n";
        // $value .="Groups=". implode(":",$requestResult["roles"]) ."\r\n";
        $value .="RemoteIP=$_SERVER[REMOTE_ADDR]\r\n";
        //$value.="Expiration=60\r\n"; //duration is handled server side; default is 1 hr and the time left refreshes on every page connection

        //store value for the key in memcache deamon
        $memcache->set($key, $value);

        //return a cookie object
        return [
            "name" => "ormsAuth",
            "key"  => $key,
        ];
    }
}
