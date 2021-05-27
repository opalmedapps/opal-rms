<?php declare(strict_types = 1);
#-------------------------------------------------
# Returns a list of resources depending on the speciality specified
#-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

$speciality = $_GET["clinic"] ?? NULL;

#get the list of possible appointments and their resources
$dbh = Database::getOrmsConnection();
$query = $dbh->prepare("
    SELECT DISTINCT
        ResourceCode AS code,
        ResourceName AS Name
    FROM
        ClinicResources
    WHERE
        Active = 1
        AND Speciality = :spec
    ORDER BY
        ResourceName
");
$query->execute([":spec" => $speciality]);

$resources = $query->fetchAll();

$resources = Encoding::utf8_encode_recursive($resources);
echo json_encode($resources);

#get the full list of appointment resources depending on the site
