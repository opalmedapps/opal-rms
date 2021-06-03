<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\SpecialityGroup;
use Orms\Http;

$specialityGroupId = $_GET["specialityGroupId"];

$id = SpecialityGroup::getSpecialityGroupCode((int) $specialityGroupId);

if($id === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown speciality group code");
}

Http::generateResponseJsonAndExit(200,[
    "specialityGroupCode" => $id
]);
