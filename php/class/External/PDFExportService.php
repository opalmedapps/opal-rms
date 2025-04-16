<?php

// SPDX-FileCopyrightText: Copyright (C) 2022 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External;

use Orms\Config;

class PDFExportService
{
    /**
     * Triggers new OpalAdmin API endpoint that generatees questionnaire PDF report and submits to the OIE.
     * @param string $mrn one of the patient's MRNs for the site
     * @param string $site one of the patient's site code for the MRN
     */
    public static function triggerQuestionnaireReportGeneration(string $mrn, string $site): void
    {
        try {
            // create curl handle
            $ch = curl_init();

            // url to generate a PDF report
            $questionnairesReviewedURL = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/questionnaires/reviewed/';

            // set url
            curl_setopt($ch, CURLOPT_URL, $questionnairesReviewedURL);

            // true to return the transfer as a string of the return value of curl_exec() instead of outputting it directly
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // https://stackoverflow.com/a/14668762
            // the number of milliseconds to wait while trying to connect.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);

            // the maximum number of milliseconds to allow cURL functions to execute
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);

            $payload = json_encode(['mrn' => $mrn, 'site' => $site]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            $sessionid = "";
            if(isset($_COOKIE["sessionid"])) {
                $sessionid = $_COOKIE["sessionid"];
            }

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type:application/json',
                    'Authorization: Token ' . $sessionid,
                )
            );

            curl_exec($ch);
        } catch (Exception $e){
            error_log((string) $e);
            return;
        }
    }
}
