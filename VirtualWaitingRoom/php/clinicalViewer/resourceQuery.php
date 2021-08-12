<?php

declare(strict_types=1);
//-------------------------------------------------
// Returns a list of resources depending on the speciality specified
//-------------------------------------------------

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Util\Encoding;

$speciality = $_GET["speciality"] ?? null;

//get the list of possible appointments and their resources
$dbh = Database::getOrmsConnection();
$query = $dbh->prepare("
    SELECT DISTINCT
        ResourceCode AS code,
        ResourceName AS Name
    FROM
        ClinicResources
    WHERE
        Active = 1
        AND SpecialityGroupId = ?
    ORDER BY
        ResourceName
");
$query->execute([$speciality]);

$resources = $query->fetchAll();

$resources = Encoding::utf8_encode_recursive($resources);
echo json_encode($resources);
