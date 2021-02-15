<?php declare(strict_types=1);

#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$speciality = $_GET["clinic"] ?? NULL;

$dbh = Database::getOrmsConnection();

$sql = "
    SELECT DISTINCT
        MV.AppointmentCode
    FROM
        MediVisitAppointmentList MV
        INNER JOIN ClinicResources ON ClinicResources.ResourceName = MV.ResourceDescription
            AND ClinicResources.Speciality = ?
    ORDER BY
        MV.AppointmentCode";

$query = $dbh->prepare($sql);
$query->execute([$speciality]);

$resources = array_map(function($x) {
    return ["Name" => $x["AppointmentCode"]];
},$query->fetchAll());

$resources = Encoding::utf8_encode_recursive($resources);
echo json_encode($resources);


?>
