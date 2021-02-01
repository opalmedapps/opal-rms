<?php declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$speciality = $_GET["speciality"];

$dbh = Config::getDatabaseConnection("ORMS");

#get the list of possible appointments and their resources
$queryClinicResources = $dbh->prepare("
    SELECT DISTINCT
        MediVisitAppointmentList.Resource,
        MediVisitAppointmentList.ResourceDescription
    FROM
        MediVisitAppointmentList
        INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
            AND ClinicResources.Speciality = :spec
    ORDER BY MediVisitAppointmentList.ResourceDescription
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
        MediVisitAppointmentList.AppointmentCode
    FROM
        MediVisitAppointmentList
    INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
        AND ClinicResources.Speciality = :spec
    ORDER BY MediVisitAppointmentList.AppointmentCode
");
$queryAppointmentCodes->execute([":spec" => $speciality]);

$appointments = array_map(function($x) {
    return $x["AppointmentCode"];
},$queryAppointmentCodes->fetchAll());

echo json_encode([
    "resources"     => utf8_encode_recursive($resources),
    "appointments"  => utf8_encode_recursive($appointments)
]);

?>
