<?php declare(strict_types=1);

#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Database;

$dbh = Database::getOpalConnection();

if($dbh === NULL)
{
    echo json_encode([]);
    exit;
}

$sql = "
SELECT DISTINCT
Name_EN
FROM
DiagnosisTranslation
ORDER BY
Name_EN";

$query = $dbh->prepare($sql);
$query->execute();

$resources = array_map(function ($x) {
return ["Name" => $x["Name_EN"]];
}, $query->fetchAll());

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);


?>
