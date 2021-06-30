<?php declare(strict_types=1);

#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\DataAccess\Database;

$speciality = $_GET["speciality"] ?? NULL;

$dbh = Database::getOrmsConnection();

$query = $dbh->prepare("
    SELECT DISTINCT
        COALESCE(DisplayName,AppointmentCode) AS AppointmentCode
    FROM
        AppointmentCode
    WHERE
        SpecialityGroupId = ?
    ORDER BY
        AppointmentCode
");
$query->execute([$speciality]);

$resources = array_map(function($x) {
    return ["Name" => $x["AppointmentCode"]];
},$query->fetchAll());

$resources = Encoding::utf8_encode_recursive($resources);
echo json_encode($resources);
