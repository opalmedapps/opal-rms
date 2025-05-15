<?php

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
            $loginURL = Config::getApplicationSettings()->environment->newOpalAdminUrl . '/api/auth/login/';
            $username = Config::getApplicationSettings()->system->newOpalAdminUsername;
            $password = Config::getApplicationSettings()->system->newOpalAdminPassword;

            // create curl handle
            $ch = curl_init();

            // set url
            curl_setopt($ch, CURLOPT_URL, $loginURL);

            # Setup request to send json via POST.
            $payload = json_encode(['username' => $username, 'password' => $password]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

            // true to mark this as a new cookie "session".
            // It will force libcurl to ignore all cookies it is about to load that are "session cookies" from the previous session.
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);

            // true to return the transfer as a string of the return value of curl_exec() instead of outputting it directly
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            $json_result = json_decode($result, true);

            if (!array_key_exists('key', $json_result))
                return;

            $token = $json_result['key'];


            // url to generate a PDF report
            $questionnairesReviewedURL = Config::getApplicationSettings()->environment->newOpalAdminUrl . '/api/questionnaires/reviewed/';


            curl_setopt($ch, CURLOPT_URL, $questionnairesReviewedURL);

            // https://stackoverflow.com/a/14668762
            // the number of milliseconds to wait while trying to connect.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);

            $payload = json_encode(['mrn' => $mrn, 'site' => $site]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // the maximum number of milliseconds to allow cURL functions to execute
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);

            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    'Content-Type:application/json',
                    'Authorization: Token ' . $token,
                )
            );

            curl_exec($ch);
        } catch (Exception $e){
            return;
        }
    }
}
