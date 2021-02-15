<?php declare(strict_types=1);
#get all speciality groups and TV hubs from the ORMS db

require __DIR__."/../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$dbh = Database::getOrmsConnection();
$query = $dbh->prepare("
    SELECT
        ClinicHub.HubId,
        ClinicHub.SpecialityGroup
    FROM ClinicHub
    ORDER BY ClinicHub.SpecialityGroup,ClinicHub.HubId
");
$query->execute();

$specialityGroups = array_reduce($query->fetchAll(),function($x,$y) {
    $x[$y["SpecialityGroup"]][] = $y["HubId"];

    return $x;
},[]);

echo json_encode(Encoding::utf8_encode_recursive($specialityGroups));

?>
