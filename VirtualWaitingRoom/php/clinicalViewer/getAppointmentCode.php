<?php
declare(strict_types=1);
#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../configFileLive.php";

$speciality = $_GET["clinic"] ?? NULL;

$dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

$sql = "
    SELECT DISTINCT
        AppointmentCode
    FROM
        MediVisitAppointmentList
        INNER JOIN ClinicResources ON ClinicResources.ResourceName = MediVisitAppointmentList.ResourceDescription
            AND ClinicResources.Speciality = ?
    ORDER BY
        AppointmentCode";

$query = $dbh->prepare($sql);
$query->execute([$speciality]);

$resources = array_map(function($x) {
    return ["Name" => $x["AppointmentCode"]];
},$query->fetchAll(PDO::FETCH_ASSOC));

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);


?>
