<?php

declare(strict_types=1);

require_once __DIR__."/../../../../../../vendor/autoload.php";

use Orms\DataAccess\Database;
use Orms\Http;

$params = Http::getRequestContents();

$patientId          = $params["patientId"] ?? null;
$user               = $params["user"] ?? null;

$dbh = Database::getOrmsConnection();
$dbh->prepare("
    INSERT INTO TEMP_PatientQuestionnaireReview(PatientSer,User)
    VALUES(:pSer,:user)
")->execute([
    ":pSer"   => $patientId,
    ":user"   => $user
]);

Http::generateResponseJsonAndExit(200);
