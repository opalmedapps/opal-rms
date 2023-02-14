<?php

declare(strict_types=1);

namespace Orms;

use Exception;
use GuzzleHttp\Client;
use Memcached;

class Authentication
{
    private static string $authUrl = "https://fedauthfcp.rtss.qc.ca/fedauth/wsapi/login";
    private static string $institution = "06-ciusss-cusm";

    public static function validateUserCredentials(string $username, string $password): bool
    {
        return true;
        //check if the credentials are valid in the AD
        $client = new Client();
        $request = $client->request("POST", self::$authUrl, [
            "form_params" => [
                "uid"           => $username,
                "pwd"           => $password,
                "institution"   => self::$institution
            ]
        ])->getBody()->getContents();

        $requestResult = json_decode($request, true);

        $roles = preg_grep("/GA-ORMS/", $requestResult["roles"] ?? []) ?: []; //filter all groups that aren't ORMS
        $statusCode = (int) ($requestResult["statusCode"] ?? 1); //if the return status is 0, then the user's credentials are valid

        return ($roles !== []) && ($statusCode === 0);
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
