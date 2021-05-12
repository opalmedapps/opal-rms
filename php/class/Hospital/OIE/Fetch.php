<?php declare(strict_types = 1);

namespace Orms\Hospital\OIE;

use Orms\Patient\Mrn;
use Orms\Hospital\OIE\Internal\Connection;

class Fetch
{
    /**
     *
     * @return mixed[]
     */
    static function getPatientDiagnosis(Mrn $patient): array
    {
        $response = Connection::getOpalHttpClient()?->request("POST","diagnosis/get/patient-diagnoses",[
            "form_params" => [
                "mrn"       => $patient->mrn,
                "site"      => $patient->site,
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

?>
