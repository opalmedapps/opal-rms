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
     *
     * @return list<array{
     *  isExternalSystem: 1,
     *  status: "Active",
     *  createdDate: string,
     *  updatedDate: string,
     *  diagnosis: array{
     *      subcode: string,
     *      subcodeDescription: string
     *  }
     * }>
     */
    public static function getPatientDiagnosis(Patient $patient): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_PATIENT_DIAGNOSIS, [
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
        return array_map(fn($x) => [
            "isExternalSystem"  => 1,
            "status"            => "Active",
            "createdDate"       => (string) $x["CreationDate"],
            "updatedDate"       => (string) $x["LastUpdated"],
            "diagnosis"         => [
                "subcode"               => (string) $x["DiagnosisCode"],
                "subcodeDescription"    => (string) $x["Description_EN"]
            ]
        ], json_decode($response, true));
    }

    /**
     *
     * @return list<array{
     *  questionId: int,
     *  questionTitle: string,
     *  questionLabel: string,
     *  answers: array<array-key,array{
     *       dateTimeAnswered: int,
     *       answer: int
     *  }>
     * }>
     */
    public static function getPatientAnswersForChartTypeQuestionnaire(Patient $patient, int $questionnaireId): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_PATIENT_QUESTIONNAIRE_ANSWERS, [
            "query" => [
                "mrn"               => $patient->getActiveMrns()[0]->mrn,
                "site"              => $patient->getActiveMrns()[0]->site,
                "questionnaireId"   => $questionnaireId,
                "visualization"     => 1
            ]
        ])?->getBody()?->getContents() ?? "[]";

        return array_map(function($x) {

            //data should be sorted in asc datetime order
            usort($x["answers"], fn($a, $b) => $a["dateTimeAnswered"] <=> $b["dateTimeAnswered"]);

            return [
                "questionId"    => (int) $x["questionId"],
                "questionTitle" => (string) $x["question_EN"],
                "questionLabel" => (string) $x["display_EN"],
                // "position"      => (int) $x["questionOrder"],
                "answers"       => array_map(fn($y) => [
                                        "dateTimeAnswered" => (int) $y["dateTimeAnswered"],
                                        "answer"           => (int) $y["answer"]
                                ], $x["answers"])
            ];
        }, json_decode($response, true));
    }

    /**
     *
     * @return list<array{
     *  questionnaireAnswerId: int,
     *  questionId: int,
     *  dateTimeAnswered: string,
     *  questionTitle: string,
     *  questionLabel: string,
     *  hasScale: bool,
     *  options: array<array-key,array{
     *      value: int,
     *      description: string
     *  }>,
     *  answers: string[]
     * }>
     */
    public static function getPatientAnswersForNonChartTypeQuestionnaire(Patient $patient, int $questionnaireId): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_PATIENT_QUESTIONNAIRE_ANSWERS, [
            "query" => [
                "mrn"               => $patient->getActiveMrns()[0]->mrn,
                "site"              => $patient->getActiveMrns()[0]->site,
                "questionnaireId"   => $questionnaireId,
                "visualization"     => 0
            ]
        ])?->getBody()?->getContents() ?? "[]";

        return array_map(fn($x) => [
            "questionnaireAnswerId" => (int) $x["answerQuestionnaireId"],
            "questionId"            => (int) $x["questionId"],
            "dateTimeAnswered"      => (string) $x["dateTimeAnswered"],
            "questionTitle"         => (string) $x["question_EN"],
            "questionLabel"         => (string) $x["display_EN"],
            // "position"              => (int) $x["questionOrder"],
            "hasScale"              => ($x["legacyTypeId"] === "2"),
            "options"               => array_map(fn($y) => [
                                            "value"       => (int) $y["value"],
                                            "description" => (string) $y["description_EN"]
                                        ], $x["options"]),
            "answers"               => array_map(fn($y) => (string) $y["answer"], $x["answers"])
        ], json_decode($response, true));
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
