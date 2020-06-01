<?php declare(strict_types = 1);
#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../configFileLive.php";

$speciality = $_GET["clinic"] ?? NULL;

#get the list of possible appointments and their resources
$resources = getResourceList($speciality);

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);

#get the full list of appointment resources depending on the site
function getResourceList(?string $speciality): array
{
    $dbh = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);
    $query = $dbh->prepare("
        SELECT DISTINCT
            MV.Resource AS code,
            CR.ResourceName AS Name,
            CR.ClinicResourcesSerNum AS resourceId,
            CR.ClinicScheduleSerNum AS scheduleId,
            CR.Speciality AS speciality
        FROM
            MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND CR.Active = 1
            AND CR.Speciality = :spec
        ORDER BY
            CR.ResourceName"
    );
    $query->execute([":spec" => $speciality]);

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

?>
