<?php declare(strict_types=1);

class HighchartsServerService
{
    CONST CHART_IMAGES_DIR = __DIR__ . '/../../../built-chart-images';
    CONST HIGHCHARTS_LOG_DIR = __DIR__ . '/../../../highcharts-export-server-log';
    CONST HIGHCHARTS_CONFIG = __DIR__ . '/../Resources/highcharts-config';
    CONST HIGHCHARTS_SERVER_HOST = '127.0.0.1';
    CONST HIGHCHARTS_SERVER_PORT = '7801';
    CONST NUMERIC_TYPE_QUESTION = 2;
    CONST TEXT_TYPE_QUESTION = 3;
    CONST ONE_LINE_LABEL = 15;
    // TODO: Add labeling type question when it's implemented in the Opal App

    /**
     * @param int $patientId
     * @param array $questionnaireAnswers
     * @return string
     */
    public function buildQuestionnaireCharts(
        $patientId,
        array $questionnaireAnswers
    ) {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $timestamp = $date->getTimestamp();

        // path to patient charts
        $patientDir = HighchartsServerService::CHART_IMAGES_DIR . '/patientID_' . $patientId . '/' . $timestamp;
//        $chartImagesDir = $patientDir . '/images';
//
//        // Check if the folder for highchart images exists
//        if (!is_dir($chartImagesDir)) {
//            // If the folder does not exist, try to create folder for chart images
//            if (!mkdir($chartImagesDir, 0777, true))
//                die('Failed to create folders...');
//        }

        $highchartsServerUrl = HighchartsServerService::HIGHCHARTS_SERVER_HOST . ':' . HighchartsServerService::HIGHCHARTS_SERVER_PORT;
        $curlArr = [];
        $jsonPaths = $this->buildJsonFilesForCharts($questionnaireAnswers, $patientDir);

        //create the multiple cURL handle
        $master = curl_multi_init();

        for ($i = 0; $i < count($jsonPaths); $i++) {
            // create cURL resources
            $curlArr[$i] = curl_init($highchartsServerUrl);
//            $cfile = new \CURLFile($jsonPaths[$i],'application/json', $jsonPaths[$i]);
            $data = file_get_contents($jsonPaths[$i]);
            // set URL and other appropriate options
            curl_setopt_array($curlArr[$i],[
                CURLOPT_CUSTOMREQUEST   => "POST",
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POSTFIELDS      => $data,
                CURLOPT_HTTPHEADER      => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ]
            ]);
            //add the handles
            curl_multi_add_handle($master, $curlArr[$i]);
        }

        //execute the handles
        do {
            curl_multi_exec($master, $running);
        } while ($running > 0);

        for ($i = 0; $i < count($jsonPaths); $i++) {
            $image = curl_multi_getcontent($curlArr[$i]);
            $jsonStr = file_get_contents($jsonPaths[$i]);
            $json = json_decode($jsonStr, true);
            $imagePath = $json["outfile"];
            file_put_contents($imagePath, $image);
        }

        //close the handles
        for ($i = 0; $i < count($jsonPaths); $i++) {
            curl_multi_remove_handle($master, $curlArr[$i]);
            curl_close($curlArr[$i]);
        }
        curl_multi_close($master);

        return 'patientID_' . $patientId . '/' . $timestamp;
    }

    /**
     * @param array $questionnaireAnswers
     * @param string $patientDir
     * @return array
     */
    private function buildJsonFilesForCharts(array $questionnaireAnswers, $patientDir) {
        $jsonFilesPathArr = [];
        $jsonConfig = '';
        //    "minorGridLineWidth": 1,
        //    "minorTickInterval": "auto",
        $yAxis = [
            "allowDecimals" => false,
            "height" => null,
            "top" => null,
            "title" => [
                "x" => -50,
                "y" => -35,
                "rotation" => 0,
                "style" => [
                    "color" => 'red',
                    "width" => 200,
                    "textOverflow" => 'ellipsis',
                    "whiteSpace" => 'nowrap'
                ],
                "useHTML" => true,
                "text" => null,
          ],
            "offset" => 5,
//            "startOnTick" => false,
//            "endOnTick" => false,
            "labels" => [
                "style" => [
                    "fontSize" => "23px"
                    ]
                ],
            "min" => null,
            "max" => null
        ];
        $series = [
            "lineWidth" => 3,
            "marker" => [
                "radius" => 8
            ],
            "showInLegend" => false,
            "data" => null,
            "yAxis" => null,
            "color" => "#7cb5ec"
        ];

        $jsonConfig = file_get_contents(HighchartsServerService::HIGHCHARTS_CONFIG . '/lineChart.json');

        if (!empty($jsonConfig)) {
            foreach ($questionnaireAnswers as $questionnaireKey => $questionnaire) {
                $yAxisId = 0;
                $top = 0; // The top position of the Y axis
                $json['infile'] = json_decode($jsonConfig, true);
                foreach ($questionnaire['questions'] as $questionKey => $question) {
                    if ($question['question_type_id'] != HighchartsServerService::NUMERIC_TYPE_QUESTION)
                        continue;

//                    $json["infile"]['title']['text'] = $question['question_text'];
                    $questionLabel = $question['question_label'];
                    $yAxis['title']['text'] = $this->getSVGLabelBox($questionLabel);
                    $yAxis['max'] = $question['max_value'];
                    $yAxis['min'] = $question['min_value'];
                    $json["infile"]["yAxis"][] = $yAxis;
                    $series["data"] = $question['values'];
                    $series["yAxis"]  = $yAxisId;
                    $json['infile']['series'][] = $series;
                    $yAxisId++;
                }

                if (empty($json['infile']['series']))
                    continue;


                // set the height and top of each chart
                foreach ($json["infile"]["yAxis"] as $yAxisKey => $yAxisChart) {
                    $height = 10; // The height of chart
                    $json["infile"]["yAxis"][$yAxisKey]["height"] = $height . "%";
                    $json["infile"]["yAxis"][$yAxisKey]["top"] = $top . "%";
                    $top += 15;
                }

                // create a json file with all charts
                //$json["outfile"] = "$patientDir/images/$questionnaireKey/$questionKey.png";
                $json["outfile"] = "$patientDir/images/$questionnaireKey/$questionnaireKey.png";
                $jsonDir = $patientDir . "/json/$questionnaireKey";
                $imagesDir = $patientDir . "/images/$questionnaireKey";

                if (!is_dir($jsonDir)) {
                    if (!mkdir($jsonDir, 0777, true))
                        die('Failed to create folders...');
                }

                if (!is_dir($imagesDir)) {
                    if (!mkdir($imagesDir, 0777, true))
                        die('Failed to create folders...');
                }

                $jsonFile = $jsonDir . "/$questionnaireKey" . ".json";
                file_put_contents($jsonFile, json_encode($json));
                array_push($jsonFilesPathArr, $jsonFile);
            }
        }
        return $jsonFilesPathArr;
    }

    /**
     * @param string $questionLabel
     * @return string
     */
    private function getSVGLabelBox($questionLabel) {
        $labelText = "<text x=50% y=50% font-family=Arial font-size=25 dominant-baseline=middle text-anchor=middle fill=white>"
                     . $questionLabel
                     . "</text>";
        // split the string on lines
        $lines = explode("\n", wordwrap($questionLabel,
                HighchartsServerService::ONE_LINE_LABEL,
                "\n",
                true)
        );

        // form lines in a label-box
        if (count($lines) == 2) { // if two lines
            $labelText = "<text x=50% y=35% font-family=Arial font-size=25 dominant-baseline=middle text-anchor=middle fill=white>"
                . utf8_encode($lines[0])
                . "</text>"
                . "<text x=50% y=75% font-family=Arial font-size=25 dominant-baseline=middle text-anchor=middle fill=white>"
                . utf8_encode($lines[1])
                . "</text>";
        } elseif (count($lines) == 3) { // if three lines
            $labelText = "<text x=50% y=25% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . $lines[0]
                . "</text>"
                . "<text x=50% y=55% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . utf8_encode($lines[1])
                . "</text>"
                . "<text x=50% y=80% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . utf8_encode($lines[2])
                . "</text>";
        }
        elseif (count($lines) > 3) { // if more than three lines
            $labelText = "<text x=50% y=25% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . $lines[0]
                . "</text>"
                . "<text x=50% y=55% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . utf8_encode($lines[1])
                . "</text>"
                . "<text x=50% y=80% font-family=Arial font-size=20 dominant-baseline=middle text-anchor=middle fill=white>"
                . strlen(utf8_encode($lines[2])) > HighchartsServerService::ONE_LINE_LABEL ?
                        substr(utf8_encode($lines[2]),0,HighchartsServerService::ONE_LINE_LABEL) . "..." : utf8_encode($lines[2])
                . "</text>";
        }

        return    "<svg width=200 height=75>"
                . "<rect x=0 y=0 width=200 height=75 stroke=black stroke-width=1px fill=blue fill-opacity=0.35 />"
                . $labelText
                . "</svg>";
    }
}

?>
