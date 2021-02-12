<?php declare(strict_types = 1);

namespace Orms;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Orms\Config;
use RuntimeException;

class Opal
{
    private static string $opalAdminUrl = "https://192.168.56.103:8085/opalAdmin/";

    static function getHttpClient(): Client
    {
        return new Client([
            "base_uri"      => self::$opalAdminUrl,
            "verify"        => FALSE,
            // "http_errors"   => FALSE
        ]);
    }

    static function getOpalSessionCookie(): CookieJar
    {
        $client = self::getHttpClient();

        $cookies = new CookieJar();
        $client->request("POST","user/system-login",[
            "form_params" => [
                "username" => "ORMS",
                "password" => "Password1@"
            ],
            "cookies" => $cookies
        ]);

        return new CookieJar(FALSE,[$cookies->getCookieByName("PHPSESSID")]);
    }

    /**
     *
     * @return mixed[]
     * @throws GuzzleException
     * @throws RuntimeException
     */
    static function getPatientDiagnosis(string $mrn): array
    {
        $response = self::getHttpClient()->request("POST","diagnosis/get/patient-diagnoses",[
            "form_params" => [
                "mrn"   => $mrn,
                "site"  => Config::getApplicationSettings()->environment->site
            ],
            "cookies" => self::getOpalSessionCookie()
        ])->getBody()->getContents();

        $data = json_decode($response);

        //filter all diagnoses originating from ORMS as we already have those in the ORMS db
        // $data = array_filter($data)

        //map the fields returned by Opal into something resembling a patient diagnosis
        // return array_map(function($x) {
        //     return [
        //         "isExternalSystem"  => 1,

        //     ];
        // },);

        return [];
    }

    static function insertPatientDiagnosis(string $mrn,int $diagId,string $diagSubcode,DateTime $creationDate,string $descEn,string $descFr): void
    {
        self::getHttpClient()->request("POST","diagnosis/insert/patient-diagnosis",[
            "form_params" => [
                "mrn"           => $mrn,
                "site"          => Config::getApplicationSettings()->environment->site,
                "source"        => "ORMS",
                "rowId"         => $diagId,
                "code"          => $diagSubcode,
                "creationDate"  => $creationDate->format("Y-m-d H:i:s"),
                "descriptionEn" => $descEn,
                "descriptionFr" => $descFr,
            ],
            "cookies" => self::getOpalSessionCookie()
        ]);
    }

    static function exportDiagnosisCode(int $id,string $code,string $desc): void
    {
        self::getHttpClient()->request("POST","master-source/insert/diagnoses",[
            "form_params" => [
                [
                    "source"        => 5, //"ORMS", // id 5
                    "externalId"    => $id,
                    "code"          => $code,
                    "description"   => $desc
                    // "creationDate"  =>
                ]
            ],
            "cookies" => self::getOpalSessionCookie()
        ]);
    }
}

?>
