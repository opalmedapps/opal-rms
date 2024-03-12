<?php

declare(strict_types=1);

namespace Orms;

use Exception;
use GuzzleHttp\Client;
use Memcached;
use Orms\Config;
use Psr\Http\Message\ResponseInterface;

class Authentication
{
    public static function login(string $username, string $password): ResponseInterface
    {
        $loginUrl = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/auth/orms/login/';

        // Check if the credentials are valid in the opalAdmin backend.
        $client = new Client();
        $response = $client->request(
            method: "POST",
            uri: $loginUrl,
            options: [
                "form_params" => [
                    "username" => $username,
                    "password" => $password,
                ],
                // disable throwing exceptions on an HTTP protocol errors
                "http_errors" => false,
            ],
        );
        //TODO: log the result of the calling API via the logLoginEvent function in Logger.

        return $response;
    }

    public static function logout(): void
    {
        // if (isset($_COOKIE["sessionid"]) && isset($_COOKIE["csrftoken"])) {
        //     // If csrftoken and sessionid cookies are not empty, logout from the Django backend.
        //     $logoutUrl = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/auth/logout/';

        //     // Call the endpoint to flush the session in opalAdmin backend.
        //     $client = new Client();
        //     $response = $client->request(
        //         method: "POST",
        //         uri: $logoutUrl,
        //         options: [
        //             'headers' => [
        //                 'cookie' => 'sessionid=' . $_COOKIE["sessionid"] . ';csrftoken=' . $_COOKIE["csrftoken"],
        //                 'x-csrftoken' => $_COOKIE["csrftoken"],
        //             ],
        //             // disable throwing exceptions on an HTTP protocol errors
        //             "http_errors" => false,
        //         ],
        //     );
        //     if (!$response || $response->getStatusCode() != 200) {
        //         // TODO: log unsuccessful logout
        //     }
        // }

        // Remove sessionid cookie
        if (isset($_COOKIE['ormsAuth'])) {
            $memcache = new Memcached(); // connect to memcached on localhost port 11211
            $memcache->addServer("ormsAuth", 11211) ?: throw new Exception("Failed to connect to memcached server");
            $memcache->delete($_COOKIE['ormsAuth']);

            unset($_COOKIE['ormsAuth']);
            setcookie(name: 'ormsAuth', value: '', expires_or_options: -1, path: '/');
        }

        // Remove csrftoken cookie
        // if (isset($_COOKIE['csrftoken'])) {
            // unset($_COOKIE['csrftoken']);
            // setcookie(name: 'csrftoken', value: '', expires_or_options: -1, path: '/');
        // }

        // Remove legacy opal admin session id
        // if (isset($_COOKIE['PHPSESSID'])) {
        //     unset($_COOKIE['PHPSESSID']);
        //     setcookie(name: 'PHPSESSID', value: '', expires_or_options: -1, path: '/');
        // }
    }

    public static function validate(string $sessionid): ResponseInterface
    {
        $validateUrl = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/auth/orms/validate/';

        // Check if the session id is valid in the opalAdmin backend.
        $client = new Client();
        $response = $client->request(
            method: "GET",
            uri: $validateUrl,
            options: [
                'headers' => [
                    'cookie' => 'sessionid=' . $sessionid,
                ],
                // disable throwing exceptions on an HTTP protocol errors
                "http_errors" => false,
            ],
        );

        return $response;
    }

    public static function createUserSession(string $username, string $sessionid): void
    {
        // Store the user session in the memcache
        $memcache = new Memcached(); // connect to memcached on localhost port 11211
        $memcache->addServer("memcached", 11211) ?: throw new Exception("Failed to connect to memcached server");

        // Construct session value to be stored in memcached for the cookie session id.
        $value = "UserName=$username\r\n";
        // $value .= "Groups=" . implode(":", $requestResult["roles"]) . "\r\n";
        $value .= "RemoteIP=$_SERVER[REMOTE_ADDR]\r\n";
        // $value .= "Expiration=60\r\n"; //duration is handled server side; default is 1 hr and the time left refreshes on every page connection
        // Store value for the key in memcache daemon
        $memcache->set($sessionid, $value);
    }
}
