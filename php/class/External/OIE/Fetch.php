<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External\OIE;

use Exception;
use Orms\DateTime;
use Orms\External\OIE\Internal\Connection;
use Orms\Patient\Model\Patient;

class Fetch
{
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
