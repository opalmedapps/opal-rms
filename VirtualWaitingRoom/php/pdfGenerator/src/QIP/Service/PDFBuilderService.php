<?php

declare(strict_types=1);
class PDFBuilderService
{
    public const PDF_ROOT_DIR = __DIR__ . "/../../../pdf-reports";
    public const LATEX_MARKUP_DIR = __DIR__ . "/../Resources/latex-markup";
    public const LATEX_MARKUP_FILE = "report.tex";
    public const LATEX_FORMAT_FILE = "report.fmt";
    public const LATEX_JOBNAME = "report";
    public const LATEX_LINE_NUMBER = 42;

    /**
     * @param int $patientId
     * @param string $patientName
     * @param string $patientMRN
     * @param array $questionnaireAnswers
     * @param string $chartImagesTimestampDir
     */
    public function buildPDF(
        $patientId,
        $patientName,
        $patientMRN,
        array $questionnaireAnswers,
        $chartImagesTimestampDir
    )
    {
        $date = new \DateTime("now", new \DateTimeZone("UTC"));
        $timestamp = $date->getTimestamp();

        // path to a pdf report directory
        $pdfReportDir = PDFBuilderService::PDF_ROOT_DIR . "/patientID_" . $patientId . "/" . $timestamp;

        // Check if the folder exists
        if (!is_dir($pdfReportDir)) {
            // If the folder does not exist, try to create pdf folder
            if (!mkdir($pdfReportDir, 0777, true))
                die("Failed to create folders...");
        }

        // create a csv file for LaTeX table
        $this->createAuxiliaryFilesForLatex(
            $questionnaireAnswers,
            $patientName,
            $patientMRN,
            $pdfReportDir,
            $chartImagesTimestampDir
        );

        $markupFile = PDFBuilderService::LATEX_MARKUP_DIR . "/" . PDFBuilderService::LATEX_MARKUP_FILE;
        $formatFile = PDFBuilderService::LATEX_MARKUP_DIR . "/" . PDFBuilderService::LATEX_FORMAT_FILE;

        // $latexExecCmd = "pdflatex -fmt $formatFile"
        //                 . " -halt-on-error -output-directory $pdfReportDir"
        //                 . " -jobname " . PDFBuilderService::LATEX_JOBNAME;
        // print("\r\n$latexExecCmd " . "$pdfReportDir/" . PDFBuilderService::LATEX_MARKUP_FILE . "\r\n");
        $latexExecCmd = "xelatex "
                        . " -halt-on-error -output-directory $pdfReportDir"
                        . " -jobname " . PDFBuilderService::LATEX_JOBNAME;
        print("\r\n$latexExecCmd " . "$pdfReportDir/" . PDFBuilderService::LATEX_MARKUP_FILE . "\r\n");

        // run latex twice so pagination is correct
        // use the draftmode to speed up compilation
        // shell_exec($latexExecCmd . " -draftmode $pdfReportDir/" . PDFBuilderService::LATEX_MARKUP_FILE);
        shell_exec($latexExecCmd . " --no-pdf $pdfReportDir/" . PDFBuilderService::LATEX_MARKUP_FILE);
        shell_exec($latexExecCmd . " $pdfReportDir/" . PDFBuilderService::LATEX_MARKUP_FILE);

        //rename pdf file
        $oldReportName = $pdfReportDir . "/" . PDFBuilderService::LATEX_JOBNAME . ".pdf";
        $newReportName = $pdfReportDir . "/" . "Report (" . $date->format("Y-m-d H:i:s") . ")" . ".pdf";

        rename($oldReportName, $newReportName);
    }


    /**
     * @param array $questionnaireAnswers
     * @param string $patientName
     * @param string $patientMRN
     * @param string $dirPath
     * @param string $chartImagesTimestampDir
     */
    private function createAuxiliaryFilesForLatex(
        array $questionnaireAnswers,
        $patientName,
        $patientMRN,
        $dirPath,
        $chartImagesTimestampDir
    )
    {
        // create csv and json files for the latex markup.
        // csv file is used to format the table on the first page of the pdf.

        $csvPath = $dirPath . "/contentTable.csv";

        $columns = [
            "questionnaireID",
            "questionnaireNickname",
            "lastUpdated"
        ];

        $file = fopen($csvPath, "w");
        fputs($file, implode(",", $columns) . "\n");

        $questionnaireArr = [];
        $questionsArr = [];
        $textQuestions = [];

        foreach ($questionnaireAnswers as $questionnaireKey => $questionnaire) {
            foreach ($questionnaire["questions"] as $questionKey => $question) {
                if ($question["question_type_id"] === HighchartsServerService::NUMERIC_TYPE_QUESTION
                ) {
                    array_push($questionsArr, $questionKey);
                }
                elseif ($question["question_type_id"] === HighchartsServerService::TEXT_TYPE_QUESTION) {
                    //text questions
                    $textAnswerValues = "{";
                    $numValueItems = count($question["values"]);
                    $i = 0;

                    foreach ($question["values"] as $value) {
                        if(++$i === $numValueItems) // if the last element
                            // divide by 1000 to convert JS timestamp to PHP timestamp
                            $textAnswerValues .= "{" . "$value[1]" . "/" . date("Y-m-d H:i", $value[0]/1000) . "}";
                        else
                            $textAnswerValues .= "{" . "$value[1]" . "/" . date("Y-m-d H:i", $value[0]/1000) . "}, ";
                    }
                    $textAnswerValues .= "}";

                    $textQuestionsStr = "{" . $question["question_text"] . "}" . "/" . $textAnswerValues;
                    array_push($textQuestions, $textQuestionsStr);
                }
            }

            // add json key-value
            if (!empty($questionsArr) || !empty($textQuestions)) {
                $lastUpdatedDateTime = new \DateTime($questionnaire["last_updated"]);
                $lastUpdated = $lastUpdatedDateTime->format("Y-m-d H:i");
                $questionnaireNickname = $this->escapeSpecialCharacters($questionnaire["questionnaire_nickname"]);
                // add a row to the csv file
                $questionnaireFields = array(
                    $questionnaireKey,
                    $questionnaireNickname,
                    $lastUpdated
                );
                // to remove double quotes, use fputs with implode
                fputs($file, implode(",", $questionnaireFields) . "\n");

                //TODO: sort $questionsArr according to the question position
                //$questionnaireArrStr = $chartImagesTimestampDir . "/$lastUpdated" . '/images/' . "$questionnaireKey/" . $questionnaireNickname . '/{' . implode(',', $questionsArr) . '}';
                $questionnaireArrStr = $chartImagesTimestampDir . "/$lastUpdated/" . $questionnaireNickname
                                        . "/$questionnaireKey" . "/images/" . "{"
                                        . (!empty($questionsArr) ? "$questionnaireKey" : "") . "}"
                                        . "/textQuestions/" . "{"
                                        . (!empty($textQuestions) ? implode(",", $textQuestions) : "")
                                        . "}";

                array_push($questionnaireArr, $questionnaireArrStr);
                $questionsArr = [];
            }
        }

        fclose($file);

        // make a copy of the .tex markup for each patient
        copy(
            PDFBuilderService::LATEX_MARKUP_DIR . "/" . PDFBuilderService::LATEX_MARKUP_FILE,
            $dirPath . "/" . PDFBuilderService::LATEX_MARKUP_FILE
        );

        $patientName = $this->escapeSpecialCharacters($patientName);
        $patientMRN = $this->escapeSpecialCharacters($patientMRN);
        $patientData = "\r\n\def\patientName{" . $patientName . "}"
                       . "\r\n\def\patientMRN{" . $patientMRN . "}"
                       . "\r\n\def\imageDirectories{" . implode(",", $questionnaireArr) . "}"
                       . "\r\n\def\csvContentTablePath{" . $dirPath . "/contentTable.csv" . "}\r\n";
        // read file into array
        $latexFile = file($dirPath . "/" . PDFBuilderService::LATEX_MARKUP_FILE, FILE_IGNORE_NEW_LINES);
        $line = $latexFile[PDFBuilderService::LATEX_LINE_NUMBER]; // read line
        array_splice($latexFile, PDFBuilderService::LATEX_LINE_NUMBER, 0, $patientData);    // insert $newline at $offset
        // put patient data to the copied tex file
        file_put_contents(
            $dirPath . "/" . PDFBuilderService::LATEX_MARKUP_FILE,
            join("\n", $latexFile)
        );    // write to file
    }

    /**
     * @param string $string
     * @return null|string|string[]
     */
    private function escapeSpecialCharacters($string)
    {
        $pattern = "/[&%$#_{}]+/";
        $replacement = "\\\$0"; // add slash before special characters
        $result = preg_replace($pattern, $replacement, $string);
        $result = preg_replace("/~+/", "\textasciitilde", $result); // replace tilde with macros
        $result = preg_replace("/\^+/", "\textasciicircum", $result); // replace circum with macros
        $result = preg_replace("/\+/", "\textbackslash", $result); // replace backslash with macros
        // TODO: replace single quote with \textquotesingle
//        $result = preg_replace('/\'/', '\textquotesingle ', $result);
        return $result;
    }
}
