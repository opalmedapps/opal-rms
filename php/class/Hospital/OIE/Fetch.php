<?php

declare(strict_types=1);

namespace Orms\Hospital\OIE;

use Exception;
use Orms\DateTime;
use Orms\Hospital\OIE\Internal\Connection;
use Orms\Hospital\OIE\Internal\ExternalPatient;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Patient;

class Fetch
{
    public static function getExternalPatientByMrnAndSite(string $mrn, string $site): ?ExternalPatient
    {
        $response = Connection::getHttpClient()?->request("POST", "patient/get", [
            "json" => [
                "mrn"  => $mrn,
                "site" => $site
            ]
        ])?->getBody()?->getContents();

        return ($response === null) ? null : self::_generateExternalPatient($response);
    }

    private static function _generateExternalPatient(string $data): ExternalPatient
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

        $insurances = array_map(function($x) {
            return new Insurance(
                $x["insuranceNumber"],
                DateTime::createFromFormatN("Y-m-d H:i:s", $x["expirationDate"]) ?? throw new Exception("Invalid insurance expiration date"),
                $x["type"],
                $x["active"]
            );
        }, $data["insurances"]);

        return new ExternalPatient(
            firstName:          $data["firstName"],
            lastName:           $data["lastName"],
            dateOfBirth:        DateTime::createFromFormatN("Y-m-d H:i:s", $data["dateOfBirth"]) ?? throw new Exception("Invalid date of birth"),
            mrns:               $mrns,
            insurances:         $insurances
        );
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
        $response = Connection::getHttpClient()?->request("GET", "Patient/Diagnosis", [
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
        $response = Connection::getOpalHttpClient()?->request("POST", "questionnaire/get/published-questionnaires")?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
        $response = Connection::getOpalHttpClient()?->request("POST", "questionnaire/get/questionnaires-list-orms", [
            "form_params" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/questionnaire/get/chart-answers-patient", [
            "form_params" => [
                "mrn"               => $patient->getActiveMrns()[0]->mrn,
                "site"              => $patient->getActiveMrns()[0]->site,
                "questionnaireId"   => $questionnaireId
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/questionnaire/get/non-chart-answers-patient", [
            "form_params" => [
                "mrn"               => $patient->getActiveMrns()[0]->mrn,
                "site"              => $patient->getActiveMrns()[0]->site,
                "questionnaireId"   => $questionnaireId
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
     *
     * @return null|array{
     *  questionnaireControlId: int,
     *  completionDate: DateTime,
     *  lastUpdated: DateTime
     * }
     */
    public static function getLastCompletedPatientQuestionnaire(Patient $patient): ?array
    {
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/questionnaire/get/last-completed-quesitonnaire", [
            "form_params" => [
                "mrn"               => $patient->getActiveMrns()[0]->mrn,
                "site"              => $patient->getActiveMrns()[0]->site
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);
        $response = json_decode($response, true);

        if($response === []) {
            return null;
        }

        return [
            "questionnaireControlId" => (int) $response["questionnaireControlId"],
            "completionDate"         => DateTime::createFromFormatN("Y-m-d H:i:s", $response["completionDate"]) ?? throw new Exception("Invalid datetime"),
            "lastUpdated"            => DateTime::createFromFormatN("Y-m-d H:i:s", $response["lastUpdated"]) ?? throw new Exception("Invalid datetime")
        ];
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
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/questionnaire/get/patients-completed-questionaires", [
            "form_params" => [
                "questionnaireList" => $questionnaireIds
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/questionnaire/get/purposes")?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

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
        $response = Connection::getOpalHttpClient()?->request("POST", "/opalAdmin/study/get/studies-patient-consented", [
            "form_params" => [
                "mrn"       => $patient->getActiveMrns()[0]->mrn,
                "site"      => $patient->getActiveMrns()[0]->site
            ]
        ])?->getBody()?->getContents() ?? "[]";
        $response = utf8_encode($response);

        return array_map(fn($x) => [
            "studyId"         => (int) $x["studyId"],
            "title"           => (string) $x["title_EN"]
        ], json_decode($response, true));
    }

}
