<?php declare(strict_types=1);

require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Database;
use Orms\DateTime;
use Orms\Hospital\MUHC\WebServiceInterface;

$ramq = $_GET["ramq"] ?? NULL;
$mrn  = $_GET["pid"] ?? NULL;

$mrnSite = Config::getApplicationSettings()->environment->site;

if($ramq === NULL && $mrn === NULL) {
    exit;
}

#connect to database
$dbh = Database::getOrmsConnection();

#format sql query based on the input mrn
if($ramq !== NULL) $searchCondition = " SSN = :identifier ";
else               $searchCondition = " PatientId = :identifier ";

$query = $dbh->prepare("
    SELECT
        LastName,
        FirstName,
        SSN,
        SSNExpDate,
        PatientId
    FROM
        Patient
    WHERE
        $searchCondition
");
$query->execute([
    ":identifier" => ($ramq !== NULL) ? $ramq : $mrn
]);

$patients = array_map(function($x) use($mrnSite) {
    return [
        "last"      => $x["LastName"],
        "first"     => $x["FirstName"],
        "ramq"      => $x["SSN"],
        "ramqExp"   => ($x["SSNExpDate"] === "0") ? NULL : DateTime::createFromFormatN("ym",$x["SSNExpDate"])?->modifyN("first day of")?->format("Y-m-d"),
        "pid"       => $x["PatientId"],
        "site"      => $mrnSite
    ];
},$query->fetchAll());

if($patients === [])
{
    if($ramq !== NULL) $patients = WebServiceInterface::findPatientByRamq($ramq);
    else               $patients = WebServiceInterface::findPatientByMrnAndSite($mrn,$mrnSite);

    $patients = array_map(function($x) use($mrnSite) {
        return [
            "last"      => $x->lastName,
            "first"     => $x->firstName,
            "ramq"      => $x->ramqNumber,
            "ramqExp"   => $x->ramqExpDate,
            "pid"       => array_values(array_filter($x->mrns,fn($y) => $y->mrnType === $mrnSite && $y->active === "1"))[0]->mrn,
            "site"      => $mrnSite
        ];
    },$patients);
}

echo json_encode([
    "record" => $patients
]);

?>
