<?php

// SPDX-FileCopyrightText: Copyright (C) 2022 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

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
            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 1,
            ]);

            $questionnairesReviewedURL = Config::getApplicationSettings()->system->newOpalAdminHostInternal . '/api/questionnaires/reviewed/';

            $response = $client->post($questionnairesReviewedURL, [
            'json' => [
                'mrn' => $mrn,
                'site' => $site,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie' => 'sessionid=' . $_COOKIE['sessionid'] . ';csrftoken=' . $_COOKIE['csrftoken'],
                'X-CSRFToken' => $_COOKIE['csrftoken'],
            ],
            ]);
        } catch (RequestException | ConnectException $e) {
            error_log($e->getMessage());
        }
    }
}
