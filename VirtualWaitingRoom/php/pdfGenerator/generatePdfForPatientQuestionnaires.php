<?php

require_once __DIR__."/../loadConfigs.php";
require_once __DIR__."/src/QIP/Service/HighchartsServerService.php";
require_once __DIR__."/src/QIP/Service/QuestionnaireScrapperService.php";
require_once __DIR__."/src/QIP/Service/PDFBuilderService.php";

$patientId = 51;// $_GET["patientId"];
$mrn = 'RVH-123456';// $_GET["mrn"];
$language = 'EN';// $_GET["language"];
$name = 'Anton Gladyr';// $_GET["name"];

$questServ = new QuestionnaireScrapperService();
$chartsServ = new HighchartsServerService();
$pdfBuilder = new PDFBuilderService();
$dbConn = new PDO(QUESTIONNIARE_CONNECT,QUESTIONNAIRE_USERNAME,QUESTIONNAIRE_PASSWORD,$QUESTIONNAIRE_OPTIONS);

$questionnaireAnswers = $questServ->fetchQuestionnaires($dbConn,$patientId,$language);

$chartImagesDir = $chartsServ->buildQuestionnaireCharts($patientId,$questionnaireAnswers);

$pdfBuilder->buildPDF($patientId,$name,$mrn,$questionnaireAnswers,$chartImagesDir);

?>