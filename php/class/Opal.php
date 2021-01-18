<?php declare(strict_types = 1);

namespace Orms;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;

use Orms\Config;

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

    static function getPatientDiagnosis(string $mrn): array
    {
        $response = self::getHttpClient()->request("POST","diagnosis/get/patient-diagnoses",[
            "form_params" => [
                "mrn"   => $mrn,
                "site"  => Config::getConfigs("orms")["SITE"]
            ],
            "cookies" => self::getOpalSessionCookie()
        ])->getBody()->getContents();

        return json_decode($response);
    }

    static function insertPatientDiagnosis(string $mrn,int $diagId,string $diagSubcode,DateTime $creationDate,string $descEn,string $descFr): array
    {
        $response = self::getHttpClient()->request("POST","diagnosis/insert/patient-diagnosis",[
            "form_params" => [
                "mrn"           => $mrn,
                "site"          => Config::getConfigs("orms")["SITE"],
                "source"        => "ORMS",
                "rowId"         => $diagId,
                "code"          => $diagSubcode,
                "creationDate"  => $creationDate->format("Y-m-d H:i:s"),
                "descriptionEn" => $descEn,
                "descriptionFr" => $descFr,
            ],
            "cookies" => self::getOpalSessionCookie()
        ])->getBody()->getContents();

        return json_decode($response);
    }

    static function exportDiagnosisCode(int $id,string $code,string $desc): void
    {
        self::getHttpClient()->request("POST","master-source/insert/diagnoses",[
            "form_params" => [
                "source"        => "ORMS",
                "externalId"    => $id,
                "code"          => $code,
                "description"   => $desc
                // "creationDate"  =>
            ],
            "cookies" => self::getOpalSessionCookie()
        ]);
    }
}

?>
