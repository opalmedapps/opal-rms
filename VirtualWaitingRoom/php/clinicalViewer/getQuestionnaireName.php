<?php

declare(strict_types=1);
#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

//require_once __DIR__."/../loadConfigs.php";
require("../loadConfigs.php");
$dbh = new PDO(OPAL_CONNECT,OPAL_USERNAME,OPAL_PASSWORD,$OPAL_OPTIONS);

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
}, $query->fetchAll(PDO::FETCH_ASSOC));

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);


?>

