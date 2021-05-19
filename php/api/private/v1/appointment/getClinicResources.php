<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$speciality = $_GET["speciality"];

$dbh = Database::getOrmsConnection();

#get the list of possible appointments and their resources
$queryClinicResources = $dbh->prepare("
    SELECT
        ResourceCode,
        ResourceName
    FROM
        ClinicResources
    WHERE
        Speciality = :spec
    ORDER BY ResourceName
");
$queryClinicResources->execute([":spec" => $speciality]);

$resources = array_map(function($x) {
    return [
        "code"          => $x["ResourceCode"],
        "description"   => $x["ResourceName"]
    ];
},$queryClinicResources->fetchAll());

$queryAppointmentCodes = $dbh->prepare("
    SELECT
        AppointmentCode
    FROM
        AppointmentCode
    WHERE
        Speciality = :spec
");
$queryAppointmentCodes->execute([":spec" => $speciality]);

$appointments = array_map(function($x) {
    return $x["AppointmentCode"];
},$queryAppointmentCodes->fetchAll());

echo json_encode([
    "resources"     => Encoding::utf8_encode_recursive($resources),
    "appointments"  => Encoding::utf8_encode_recursive($appointments)
]);

?>
