<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$speciality = $_GET["speciality"];

$dbh = Database::getOrmsConnection();

#get the list of possible appointments and their resources
$queryClinicResources = $dbh->prepare("
    SELECT DISTINCT
        MV.Resource,
        MV.ResourceDescription
    FROM
        MediVisitAppointmentList MV
        INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            AND ClinicResources.Speciality = :spec
    ORDER BY MV.ResourceDescription
");
$queryClinicResources->execute([":spec" => $speciality]);

$resources = array_map(function($x) {
    return [
        "code"          => $x["Resource"],
        "description"   => $x["ResourceDescription"]
    ];
},$queryClinicResources->fetchAll());

$queryAppointmentCodes = $dbh->prepare("
    SELECT DISTINCT
        MV.AppointmentCode
    FROM
        MediVisitAppointmentList MV
    INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
        AND ClinicResources.Speciality = :spec
    ORDER BY MV.AppointmentCode
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
