<?php

require_once __DIR__."/../../../vendor/autoload.php";
require_once __DIR__."/../loadConfigs.php";
require_once __DIR__."/src/QIP/Service/HighchartsServerService.php";
require_once __DIR__."/src/QIP/Service/QuestionnaireScrapperService.php";
require_once __DIR__."/src/QIP/Service/PDFBuilderService.php";

use Orms\Config;

$patientId = 51;// $_GET["patientId"];
$mrn = 'RVH-123456';// $_GET["mrn"];
$language = 'EN';// $_GET["language"];
$name = 'Anton Gladyr';// $_GET["name"];

$questServ = new QuestionnaireScrapperService();
$chartsServ = new HighchartsServerService();
$pdfBuilder = new PDFBuilderService();
$dbConn = Config::getDatabaseConnection("QUESTIONNAIRE");

$questionnaireAnswers = $questServ->fetchQuestionnaires($dbConn,$patientId,$language);

$chartImagesDir = $chartsServ->buildQuestionnaireCharts($patientId,$questionnaireAnswers);

$pdfBuilder->buildPDF($patientId,$name,$mrn,$questionnaireAnswers,$chartImagesDir);

?>
