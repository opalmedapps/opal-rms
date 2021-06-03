<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Http;
use Orms\Database;

$specialityGroupId = $_GET["specialityGroupId"];

$dbh = Database::getOrmsConnection();

#get the list of possible appointments and their resources
$queryClinicResources = $dbh->prepare("
    SELECT
        ResourceCode,
        ResourceName,
        SourceSystem
    FROM
        ClinicResources
    WHERE
        SpecialityGroupId = ?
    ORDER BY ResourceName
");
$queryClinicResources->execute([$specialityGroupId]);

$resources = array_map(function($x) {
    return [
        "code"          => $x["ResourceCode"],
        "description"   => $x["ResourceName"],
        "system"        => $x["SourceSystem"]
    ];
},$queryClinicResources->fetchAll());

$queryAppointmentCodes = $dbh->prepare("
    SELECT
        AppointmentCode,
        SourceSystem
    FROM
        AppointmentCode
    WHERE
        SpecialityGroupId = ?
");
$queryAppointmentCodes->execute([$specialityGroupId]);

$appointments = array_map(function($x) {
    return [
        "code"   => $x["AppointmentCode"],
        "system" => $x["SourceSystem"]
    ];
},$queryAppointmentCodes->fetchAll());

Http::generateResponseJsonAndExit(200,[
    "resources"     => Encoding::utf8_encode_recursive($resources),
    "appointments"  => Encoding::utf8_encode_recursive($appointments)
]);
