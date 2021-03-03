<?php declare(strict_types = 1);

namespace Orms;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

use Orms\Config;
use Orms\DateTime;

Opal::__init();

class Opal
{
    private static ?string $opalAdminUrl;

    public static function __init(): void
    {
        $url = Config::getApplicationSettings()->opal?->opalAdminUrl;

        if($url === NULL) {
            self::$opalAdminUrl = NULL;
        }
        else {
            self::$opalAdminUrl = $url . (substr($url,-1) === "/" ? "" : "/"); //add trailing slash if there is none
        }
    }

    static function getHttpClient(): Client
    {
        return new Client([
            "base_uri"      => self::$opalAdminUrl,
            "verify"        => FALSE, //this should be changed at some point...
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
        if(self::$opalAdminUrl === NULL) return [];

        $response = self::getHttpClient()->request("POST","diagnosis/get/patient-diagnoses",[
            "form_params" => [
                "mrn"       => $mrn,
                "site"      => Config::getApplicationSettings()->environment->site,
                "source"    => "ORMS",
                "include"   => 0,
                "startDate" =>"2000-01-01",
                "endDate"   =>"2099-12-31"
            ],
            "cookies" => self::getOpalSessionCookie()
        ])->getBody()->getContents();

        //map the fields returned by Opal into something resembling a patient diagnosis
        return array_map(function($x) {
            return [
                "isExternalSystem"  => 1,
                "status"            => "Active",
                "createdDate"       => $x["CreationDate"],
                "updatedDate"       => $x["LastUpdated"],
                "diagnosis"         => [
                    "subcode"               => $x["DiagnosisCode"],
                    "subcodeDescription"    => $x["Description_EN"]
                ]
            ];
        },json_decode($response,TRUE));
    }

    static function insertPatientDiagnosis(string $mrn,int $diagId,string $diagSubcode,DateTime $creationDate,string $descEn,string $descFr): void
    {
        if(self::$opalAdminUrl === NULL) return;

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

    static function deletePatientDiagnosis(string $mrn,int $diagId): void
    {
        if(self::$opalAdminUrl === NULL) return;

        self::getHttpClient()->request("POST","diagnosis/delete/patient-diagnosis",[
            "form_params" => [
                "mrn"           => $mrn,
                "site"          => Config::getApplicationSettings()->environment->site,
                "source"        => "ORMS",
                "rowId"         => $diagId
            ],
            "cookies" => self::getOpalSessionCookie()
        ]);
    }

    static function exportDiagnosisCode(int $id,string $code,string $desc): void
    {
        if(self::$opalAdminUrl === NULL) return;

        self::getHttpClient()->request("POST","master-source/insert/diagnoses",[
            "form_params" => [
                [
                    "source"        => "ORMS",
                    "externalId"    => $id,
                    "code"          => $code,
                    "description"   => $desc
                ]
            ],
            "cookies" => self::getOpalSessionCookie()
        ]);
    }
}

?>
