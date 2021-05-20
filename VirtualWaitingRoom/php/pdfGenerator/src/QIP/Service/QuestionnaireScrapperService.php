<?php declare(strict_types=1);

class QuestionnaireScrapperService
{
    CONST QUESTIONNAIRES = 0;
    CONST SECTIONS = 1;
    CONST QUESTIONS = 2;
    CONST ANSWERS = 3;
    CONST STATUS = 4;

    /**
     * @param PDO $dbConnection
     * @param int $patientId
     * @param string $lang
     * @return array
     */
    public function fetchQuestionnaires(PDO $dbConnection, $patientId, $lang)
    {
        $results = [];
        $answerArray = [];

        $stmt = $dbConnection->prepare("CALL getCompletedQuestionnairesList($patientId, '$lang')");
        $stmt->execute();

        // TODO: Refactor fetching
        while ($stmt->columnCount()) {
            $rowset = $stmt->fetchAll();
            if ($rowset) {
                array_push($results, $rowset);
                $stmt->nextRowset();
            }
        }

        // TODO: the next block slows down runtime. Must be refactored
        // check if array with the code of the status of the procedure exists
        if (array_key_exists(QuestionnaireScrapperService::STATUS, $results)) {
            // check if returned status of the procedure is 0
            if ($results[QuestionnaireScrapperService::STATUS][0]['procedure_status'] == 0) {
                // set time zone for converting strings (last_updated) to dates. It is necessary for finding
                // the latest date and sorting the resulting array by date
                date_default_timezone_set('UTC');
                foreach ($results[QuestionnaireScrapperService::ANSWERS] as $answer) {
                    //TODO: add section groups

                    // check if answer value is null
                    if (is_null($answer['answer_value']))
                        continue;

                    $questionnaire = $answer['questionnaire_id'];
                    $questionID = $answer['question_id'];
                    $lastUpdated = $answer['last_updated'];

                    // find an id of row (key) in questionnaires result set by questionnaire_id
                    $key = array_search($answer['questionnaire_id'],
                        array_column($results[QuestionnaireScrapperService::QUESTIONNAIRES],
                            'questionnaire_id'));
                    // take questionnaire nickname
                    $answerArray[$questionnaire]['questionnaire_id'] = $answer['questionnaire_id'];
                    $answerArray[$questionnaire]['questionnaire_nickname'] = $results[QuestionnaireScrapperService::QUESTIONNAIRES][$key]['nickname'];

                    // find array of ids (keys) in answers result set by questionnaire_id
                    $keys = array_keys(array_column($results[QuestionnaireScrapperService::ANSWERS], 'questionnaire_id'), $answer['questionnaire_id']);
                    // return array of rows (answers) by keys
                    $answersData = array_intersect_key($results[QuestionnaireScrapperService::ANSWERS], array_flip($keys));
                    // return last_updated column (dates)
                    $dates = array_column($answersData, 'last_updated');
                    // convert strings to date time values
                    $dateTimeArr = array_map('strtotime', $dates);
                    // find the latest date
                    // retrieve every key related to a given max value; index of the highest value in $maxs[0]
                    $maxs = array_keys($dateTimeArr, max($dateTimeArr));
                    $answerArray[$questionnaire]['last_updated'] = $dates[$maxs[0]];

                    // find an id of row (key) in questions result set by question_id
                    $key = array_search($answer['question_id'], array_column($results[QuestionnaireScrapperService::QUESTIONS], 'question_id'));
                    $answerArray[$questionnaire]['questions'][$questionID]['question_text'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['question_text'];
                    $answerArray[$questionnaire]['questions'][$questionID]['question_label'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['question_label'];
                    $answerArray[$questionnaire]['questions'][$questionID]['question_type_id'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['type_id'];
                    $answerArray[$questionnaire]['questions'][$questionID]['position'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['question_position'];
                    $answerArray[$questionnaire]['questions'][$questionID]['min_value'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['min_value'];
                    $answerArray[$questionnaire]['questions'][$questionID]['max_value'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['max_value'];
                    $answerArray[$questionnaire]['questions'][$questionID]['polarity'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['polarity'];
                    $answerArray[$questionnaire]['questions'][$questionID]['section_id'] = $results[QuestionnaireScrapperService::QUESTIONS][$key]['section_id'];
                    $dtime = \DateTime::createFromFormat('Y-m-d H:i:s', $lastUpdated);
                    // JavaScript uses milliseconds as a timestamp, whereas PHP uses seconds
                    // multiply by 1000 to get the timestamp for JavaScript
                    $timestamp = $dtime->getTimestamp() * 1000;
                    $answerValue = null;

                    if (is_numeric($answer['answer_value']))
                        $answerValue = (float)$answer['answer_value'];
                    else
                        $answerValue = $answer['answer_value'];

                    $answerArray[$questionnaire]['questions'][$questionID]['values'][] = [$timestamp, $answerValue];
                }

                // sort questionnaires by date
                usort($answerArray, [$this, 'dateCompare']);
            }
        }

        return $answerArray;
    }

    /**
     * @param array $a
     * @param array $b
     * @return false|int
     */
    private function dateCompare(array $a, array $b)
    {
        $t1 = strtotime($a['last_updated']);
        $t2 = strtotime($b['last_updated']);
        return $t2 - $t1;
    }
}
