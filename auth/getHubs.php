<?php declare(strict_types=1);
#get all speciality groups and TV hubs from the ORMS db

require __DIR__."/../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$dbh = Database::getOrmsConnection();
$query = $dbh->prepare("
    SELECT
        CH.ClinicHubId,
        CH.ClinicHubName,
        SG.SpecialityGroupId,
        SG.SpecialityGroupName
    FROM
        ClinicHub CH
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CH.SpecialityGroupId
    ORDER BY
        SG.SpecialityGroupName,
        CH.ClinicHubName
");
$query->execute();

$specialityGroups = array_reduce($query->fetchAll(),function($x,$y) {
    $x[$y["SpecialityGroupName"]][] = $y;

    return $x;
},[]);

echo json_encode(Encoding::utf8_encode_recursive($specialityGroups));
