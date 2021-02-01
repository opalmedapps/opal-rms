<?php declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Hospital\MUHC\WebServiceInterface;

$ramq = $_GET["ramq"] ?? NULL;
$mrn  = $_GET["pid"] ?? NULL;

$mrnSite = Config::getConfigs("orms")["SITE"];

if($ramq === NULL && $mrn === NULL) {
    exit;
}

#connect to database
$dbh = Config::GetDatabaseConnection("ORMS");

#format sql query based on the input mrn
if($ramq !== NULL) $searchCondition = " SSN = :identifier ";
else               $searchCondition = " PatientId = :identifier ";

$query = $dbh->prepare("
    SELECT
        LastName,
        FirstName,
        SSN,
        CASE WHEN SSNExpDate = 0 THEN '0000' ELSE SSNExpDate END AS SSNExpDate,
        PatientId
    FROM
        Patient
    WHERE
        $searchCondition
");
$query->execute([
    ":identifier" => ($ramq !== NULL) ? $ramq : $mrn
]);

$patients = array_map(function($x) {
    return [
        "last"      => $x["LastName"],
        "first"     => $x["FirstName"],
        "ramq"      => $x["SSN"],
        "ramqExp"   => $x["SSNExpDate"],
        "pid"       => $x["PatientId"],
    ];
},$query->fetchAll());

if($patients === [])
{
    if($ramq !== NULL) $patients = WebServiceInterface::findPatientByRamq($ramq);
    else               $patients = WebServiceInterface::findPatientByMrnAndSite($mrn,$mrnSite);
}

echo json_encode([
    "record" => $patients
]);

?>
