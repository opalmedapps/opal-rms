<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE;

use Exception;
use Orms\DateTime;
use Orms\Patient\Model\Patient;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Insurance;
use Orms\Hospital\OIE\Internal\Connection;
use Orms\Hospital\OIE\Internal\ExternalPatient;

class Fetch
{
    static function getExternalPatientByMrnAndSite(string $mrn,string $site): ?ExternalPatient
    {
        $response = Connection::getHttpClient()?->request("POST","patient/get",[
            "json" => [
                "mrn"  => $mrn,
                "site" => $site
            ]
        ])?->getBody()?->getContents();

        return ($response === NULL) ? NULL : self::_generateExternalPatient($response);
    }

    private static function _generateExternalPatient(string $data): ExternalPatient
    {
        $data = json_decode($data,TRUE)["data"];

        foreach($data as &$x) {
            if(is_string($x) === TRUE && ctype_space($x) || $x === "") $x = NULL;
        }

        $mrns = array_map(function($x) {
            return new Mrn(
                $x["mrn"],
                $x["site"],
                $x["active"]
            );
        },$data["mrns"]);

        $insurances = array_map(function($x) {
            return new Insurance(
                $x["insuranceNumber"],
                DateTime::createFromFormatN("Y-m-d H:i:s",$x["expirationDate"]) ?? throw new Exception("Invalid insurance expiration date"),
                $x["type"],
                $x["active"]
            );
        },$data["insurances"]);

        return new ExternalPatient(
            firstName:          $data["firstName"],
            lastName:           $data["lastName"],
            dateOfBirth:        DateTime::createFromFormatN("Y-m-d H:i:s",$data["dateOfBirth"]) ?? throw new Exception("Invalid date of birth"),
            mrns:               $mrns,
            insurances:         $insurances
        );
    }

    /**
     *
     * @return mixed[]
     */
    static function getPatientDiagnosis(Patient $patient): array
    {
        $response = Connection::getHttpClient()?->request("GET","Patient/Diagnosis",[
            "json" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site,
                "source"    => "ORMS",
                "include"   => 0,
                "startDate" => "1970-01-01",
                "endDate"   => "2099-12-31"
            ]
        ])?->getBody()?->getContents() ?? "[]";

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

}
