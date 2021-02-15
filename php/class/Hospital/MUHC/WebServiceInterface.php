<?php declare(strict_types = 1);

namespace Orms\Hospital\MUHC;

use Exception;
use SoapClient;

use Orms\Config;
use Orms\DateTime;

use Orms\Hospital\MUHC\Patient;

//Class that connects to the hospital ADT webservices
// webservices are called with xml requests

class WebServiceInterface
{
    /** @var array<string,int> */
    private static $soapOptions = [
        "trace" => 1,
        "features" => SOAP_SINGLE_ELEMENT_ARRAYS,
    ];

    /** @return Patient[] */
    static function findPatientByMrn(string $mrn): array
    {
        return self::_makeAdtCall("findByMrn",[
            "mrns"       => $mrn,
        ]);
    }

    /** @return Patient[] */
    static function findPatientByMrnAndSite(string $mrn,string $site): array
    {
        return self::_makeAdtCall("getPatient",[
            "mrn"       => $mrn,
            "mrnType"   => self::_getOacisCodeFromSiteCode($site)
        ]);
    }

    /** @return Patient[] */
    static function findPatientByRamq(string $ramq): array
    {
        return self::_makeAdtCall("findByRamq",["ramqs" => $ramq]);
    }

    /**
     *
     * @param array<string,string> $params
     * @return Patient[]
     */
    private static function _makeAdtCall(string $func,array $params): array
    {
        #make the call to the specified webservice with an xml request
        $url = Config::getApplicationSettings()->muhc?->pdsUrl;
        try {
            $client = new SoapClient($url,self::$soapOptions);
            $patients = $client->$func($params)->return ?? []; #returns an array of patient objects
        }
        catch(\SoapFault) {
            $patients = [];
        }

        $patients = json_decode(json_encode($patients) ?: "",TRUE); #encode and decode to transform the response object into an array

        //filter duplicate entries
        $patients = array_unique($patients,SORT_REGULAR);

        //sometimes newborns are given the RAMQ of the mother; these entries have a marital status of NewBorn
        //filter them if they exist
        $patients = array_filter($patients,fn($x) => ($x["maritalStatus"] ?? NULL) === "NewBorn");

        return array_map(function($x) {
            $mrns = array_map(function($y) {
                return new Mrn(
                    active:       $y["active"],
                    lastUpdate:   $y["lastUpdate"],
                    mrn:          $y["mrn"],
                    mrnType:      $y["mrnType"],
                );
            },$x["mrns"]);

            // $x["mrnType"] = self::_getSiteCodeFromOacisCode($x["mrnType"]);
            // "ramqExpDate"   => preg_replace("/T.*/","",$x["ramqExpDate"] ?? NULL) ?: NULL,

            return new Patient(
                birthDt:                $x["birthDt"],
                birthPlace:             $x["birthPlace"],
                fatherFirstName:        $x["fatherFirstName"],
                fatherLastName:         $x["fatherLastName"],
                firstName:              $x["firstName"],
                height:                 $x["height"],
                heightUnit:             $x["heightUnit"],
                homeAddCity:            $x["homeAddCity"],
                homeAddPostalCode:      $x["homeAddPostalCode"],
                homeAddProvince:        $x["homeAddProvince"],
                homeAddress:            $x["homeAddress"],
                homePhoneNumber:        $x["homePhoneNumber"],
                internalId:             $x["internalId"],
                lastName:               $x["lastName"],
                maritalStatus:          $x["maritalStatus"],
                motherFirstName:        $x["motherFirstName"],
                mrns:                   $mrns,
                motherLastName:         $x["motherLastName"],
                motherMaidenName:       $x["motherMaidenName"],
                otherNameType:          $x["otherNameType"],
                primaryLanguage:        $x["primaryLanguage"],
                ramqExpDate:            $x["ramqExpDate"],
                ramqNumber:             $x["ramqNumber"],
                sex:                    $x["sex"],
                spouseFirstName:        $x["spouseFirstName"],
                spouseLastName:         $x["spouseLastName"],
            );
        },$patients);
    }

    private static function _getSiteCodeFromOacisCode(string $code): string
    {
        return match($code) {
            "MR_PCS" => "RVH",
            "MG_PCS" => "MGH",
            "MC_ADT" => "MCH",
            "LA_ADT" => "LAC",
            default  => throw new Exception("Unknown Oacis code")
        };
    }

    private static function _getOacisCodeFromSiteCode(string $code): string
    {
        return match($code) {
            "RVH"   => "MR_PCS",
            "MGH"   => "MG_PCS",
            "MCH"   => "MC_ADT",
            "LAC"   => "LA_ADT",
            default => throw new Exception("Unknown hospital code")
        };
    }
}

?>
