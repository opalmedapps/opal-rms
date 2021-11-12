<?php

declare(strict_types=1);

namespace Orms\External\OIE;

use Exception;
use Orms\DateTime;
use Orms\External\OIE\Internal\Connection;
use Orms\External\OIE\Internal\ExternalPatient;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Patient;

class Fetch
{
    public static function generateExternalPatient(string $data): ExternalPatient
    {
        $data = json_decode($data, true)["data"];

        foreach($data as &$x) {
            if(is_string($x) === true && ctype_space($x) || $x === "") $x = null;
        }

        $mrns = array_map(function($x) {
            return new Mrn(
                $x["mrn"],
                $x["site"],
                $x["active"]
            );
        }, $data["mrns"]);

        if($data["ramq"] !== null && $data["ramqExpiration"] !== null) {
            $insurances = [
                new Insurance(
                    $data["ramq"],
                    DateTime::createFromFormatN("Y-m-d H:i:s",$data["ramqExpiration"]) ?? throw new Exception("Invalid date of birth"),
                    "RAMQ",
                    true
                )
            ];
        }
        else {
            $insurances = [];
        }

        return new ExternalPatient(
            firstName:          $data["firstName"],
            lastName:           $data["lastName"],
            dateOfBirth:        DateTime::createFromFormatN("Y-m-d H:i:s", $data["dateOfBirth"]) ?? throw new Exception("Invalid date of birth"),
            mrns:               $mrns,
            insurances:         $insurances
        );
    }

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
     *    questionnaireId: int,
     *    name: string
     * }>
     */
    public static function getListOfQuestionnaires(): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_QUESTIONNAIRE_PUBLISHED)?->getBody()?->getContents() ?? "[]";

        return array_map(fn($x) => [
            "questionnaireId"  => (int) $x["ID"],
            "name"             => (string) $x["name_EN"]
        ], json_decode($response, true));
    }

    /**
     *
     * @return array<array-key, array{
     *      completionDate: string,
     *      completed: true,
     *      purposeId: int,
     *      purposeTitle: string,
     *      questionnaireId: int,
     *      questionnaireName: string,
     *      respondentId: int,
     *      respondentTitle: string,
     *      status: string,
     *      visualization: int,
     *      studyIdList: int[]
     * }>
     */
    public static function getListOfCompletedQuestionnairesForPatient(Patient $patient): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_PATIENT_QUESTIONNAIRE_COMPLETED, [
            "query" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site
            ]
        ])?->getBody()?->getContents() ?? "[]";

        return array_map(fn($x) => [
            "completionDate"        => (string) $x["completionDate"],
            "completed"             => true,
            "status"                => (string) $x["status"],
            "questionnaireId"       => (int) $x["questionnaireDBId"],
            "questionnaireName"     => (string) $x["name_EN"],
            "visualization"         => (int) $x["visualization"],
            "purposeId"             => (int) $x["purposeId"],
            "respondentId"          => (int) $x["respondentId"],
            "purposeTitle"          => (string) $x["purpose_EN"],
            "respondentTitle"       => (string) $x["respondent_EN"],
            "studyIdList"           => array_map("intval", $x["studies"] ?? [])
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
     * @param Patient[] $patients
     * @return array<int,null|array{
     *  questionnaireControlId: int,
     *  completionDate: DateTime,
     *  lastUpdated: DateTime
     * }>
     */
    public static function getLastCompletedQuestionnaireForPatients(array $patients): ?array
    {
        $lastCompletedQuestionnaires = []; //hash of last completed questionnaires with patient ids as the keys

        $response = Connection::getHttpClient()?->request("GET",Connection::API_PATIENT_QUESTIONNAIRE_COMPLETED, [
            "json" => [
                "last"      => true, //by putting this parameter, we get a different response from the api
                "patient"   => array_map(fn($p) => [
                                    "mrn"   => $p->getActiveMrns()[0]->mrn,
                                    "site"  => $p->getActiveMrns()[0]->site,
                                ], $patients)
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = json_decode($response, true);

        foreach($patients as $index => $p) {
            $r = ($response[$index] ?? null) ?: null; //response for an individual patient may be false if the patient has no questionnaires
            if($r !== null) {
                $r = [
                    "questionnaireControlId" => (int) $r["questionnaireControlId"],
                    "completionDate"         => DateTime::createFromFormatN("Y-m-d H:i:s", $r["completionDate"]) ?? throw new Exception("Invalid datetime"),
                    "lastUpdated"            => DateTime::createFromFormatN("Y-m-d H:i:s", $r["lastUpdated"]) ?? throw new Exception("Invalid datetime")
                ];
            }

            $lastCompletedQuestionnaires[$p->id] = $r;
        }

        return $lastCompletedQuestionnaires;
    }

    /**
     *
     * @param int[] $questionnaireIds
     * @return list<array{
     *   mrn: string,
     *   site: string,
     *   completionDate: DateTime,
     *   lastUpdated: DateTime
     * }>
     */
    public static function getPatientsWhoCompletedQuestionnaires(array $questionnaireIds): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_QUESTIONNAIRE_PATIENT_COMPLETED, [
            "query" => [
                "questionnaires" => $questionnaireIds
            ]
        ])?->getBody()?->getContents() ?? "[]";

        return array_map(fn($x) => [
            "mrn"             => (string) $x["mrn"],
            "site"            => (string) $x["site"],
            "completionDate"  => DateTime::createFromFormatN("Y-m-d H:i:s", $x["completionDate"]) ?? throw new Exception("Invalid datetime"),
            "lastUpdated"     => DateTime::createFromFormatN("Y-m-d H:i:s", $x["lastUpdated"]) ?? throw new Exception("Invalid datetime")
        ], json_decode($response, true));
    }

    /**
     *
     * @return list<array{
     *   purposeId: int,
     *   title: string,
     * }>
     */
    public static function getQuestionnairePurposes(): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_QUESTIONNAIRE_PURPOSE)?->getBody()?->getContents() ?? "[]";

        $response = array_filter(json_decode($response, true), fn($x) => in_array($x["title_EN"], ["Clinical","Research"]));
        return array_map(fn($x) => [
            "purposeId"       => (int) $x["ID"],
            "title"           => (string) $x["title_EN"]
        ], $response);
    }

    /**
     *
     * @return list<array{
     *   studyId: int,
     *   title: string
     * }>
     */
    public static function getStudiesForPatient(Patient $patient): array
    {
        $response = Connection::getHttpClient()?->request("GET", Connection::API_PATIENT_QUESTIONNAIRE_STUDY, [
            "query" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site
            ]
        ])?->getBody()?->getContents() ?? "[]";

        return array_map(fn($x) => [
            "studyId"         => (int) $x["studyId"],
            "title"           => (string) $x["title_EN"]
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
