<?php declare(strict_types=1);

#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Config;

$dbh = Config::getDatabaseConnection("OPAL");

$sql = "
SELECT DISTINCT
QuestionnaireName_EN
FROM
QuestionnaireControl
ORDER BY
QuestionnaireName_EN";

$query = $dbh->prepare($sql);
$query->execute();

$resources = array_map(function ($x) {
    return ["Name" => $x["QuestionnaireName_EN"]];
}, $query->fetchAll());

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);


?>
