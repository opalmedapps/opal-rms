<?php

declare(strict_types=1);

namespace Orms\External\OIE;

use Exception;
use Orms\DateTime;
use Orms\External\OIE\Internal\Connection;
use Orms\Patient\Model\Patient;

class Fetch
{

    /**
     * Returns an array where the first element is the mrn and the second is the site.
     *
     * @return array{
     *  0: ?string,
     *  1: ?string,
     * }
     */
    public static function getMrnSiteOfAppointment(string $sourceId,string $sourceSystem): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_APPOINTMENT_MRN, [
            "json" => [
                "sourceId"     => $sourceId,
                "sourceSystem" => $sourceSystem
            ]
        ])?->getBody()?->getContents() ?? "[]";

        $response = json_decode($response,true);
        $response = $response[0] ?? []; //order is chronologically desc

        $mrn = $response["mrn"] ?? null;
        $site = $response["site"] ?? null;

        return [
            ($mrn === null) ? null : (string) $mrn,
            ($site === null) ? null : (string) $site
        ];
    }

    /**
     *  Checks in the Aria system wether a patient has a photo. A null value indicates that the patient is not an Aria patient
     */
    public static function checkAriaPhotoForPatient(Patient $patient): ?bool
    {
        $response = Connection::getHttpClient()?->request("POST", Connection::API_ARIA_PHOTO, [
            "json" => [
                "mrn"       => array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->mrn ?? null,
                "site"      => array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->site ?? null
            ],
            "http_errors"   => FALSE,
        ]);

        $hasPhoto = null;

        if($response !== null && $response->getStatusCode() === 200) {
            $body = $response->getBody()->getContents();
            $body = json_decode($body, true);

            $hasPhoto = $body["hasPhoto"] ?? null;
        }

        return $hasPhoto;
    }

}
