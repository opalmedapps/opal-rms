<?php

declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";
require_once __DIR__."/src/QIP/Service/HighchartsServerService.php";
require_once __DIR__."/src/QIP/Service/QuestionnaireScrapperService.php";
require_once __DIR__."/src/QIP/Service/PDFBuilderService.php";

use Orms\Config;
use Orms\DataAccess\Database;

$patientId = 51;// $param["patientId"];
$mrn = "RVH-123456";// $param["mrn"];
$language = "EN";// $param["language"];
$name = "Anton Gladyr";// $param["name"];

$questServ = new QuestionnaireScrapperService();
$chartsServ = new HighchartsServerService();
$pdfBuilder = new PDFBuilderService();
$dbConn = Database::getQuestionnaireConnection();

$questionnaireAnswers = $questServ->fetchQuestionnaires($dbConn, $patientId, $language);

$chartImagesDir = $chartsServ->buildQuestionnaireCharts($patientId, $questionnaireAnswers);

$pdfBuilder->buildPDF($patientId, $name, $mrn, $questionnaireAnswers, $chartImagesDir);
