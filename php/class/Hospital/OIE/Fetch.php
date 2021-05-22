<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE;

use Exception;
use Orms\DateTime;
use Orms\Patient\Patient;
use Orms\Hospital\OIE\Internal\Connection;
use Orms\Hospital\OIE\Internal\ExternalInsurance;
use Orms\Hospital\OIE\Internal\ExternalPatient;
use Orms\Hospital\OIE\Internal\ExternalMrn;

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

    static function getExternalPatientByRamq(string $ramq): ?ExternalPatient
    {
        $response = Connection::getHttpClient()?->request("POST","patient/get",[
            "json" => [
                "ramq"  => $ramq
            ]
        ])?->getBody()?->getContents();

        return ($response === NULL) ? NULL : self::_generateExternalPatient($response);
    }

    private static function _generateExternalPatient(string $data): ExternalPatient
    {
        $data = json_decode($data,TRUE);

        $mrns = array_map(function($x) {
            return new ExternalMrn(
                $x["mrn"],
                $x["site"],
                $x["active"]
            );
        },$data["mrns"]);

        $insurances = [];
        $ramq = $data["ramq"] ?? NULL;
        $ramqExpiration = $data["ramqExpiration"] ?? NULL;

        if($ramq !== NULL && $ramqExpiration !== NULL) {
            $insurances[] = new ExternalInsurance(
                $ramq,
                DateTime::createFromFormatN("Y-m-d H:i:s",$ramqExpiration) ?? throw new Exception("Invalid ramq expiration date"),
                "RAMQ",
                TRUE
            );
        }

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
        $response = Connection::getOpalHttpClient()?->request("POST","diagnosis/get/patient-diagnoses",[
            "form_params" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site,
                "source"    => "ORMS",
                "include"   => 0,
                "startDate" =>"2000-01-01",
                "endDate"   =>"2099-12-31"
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
