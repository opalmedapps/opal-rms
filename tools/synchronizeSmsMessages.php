<?php declare(strict_types = 1);

/*
    This script takes in an input csv file and updates the SmsMessages db table to match it's contents. It also exports the updated table to a csv file (to account for new codes that have been added).
*/

require_once __DIR__ ."/../vendor/autoload.php";

use Orms\Util\Csv;

#load csv file from command line input

#get csv file name from command line arguments and load it
$csvFile = (getopt("",["file:"]))["file"] ?: "";

$combinations = Csv::loadCsvFromFile($csvFile);

$dbh = Database::getOrmsConnection();
$dbh->beginTransaction();

#update the db
$queryUpdateComb = $dbh->prepare("
    UPDATE SmsAppointment SA
    INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = SA.ClinicResourcesSerNum
    INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = SA.AppointmentCodeId
    SET
        SA.Type = :type,
        SA.Active = :act
    WHERE
        CR.ResourceCode = :rCode
        AND CR.ResourceName = :rName
        AND AC.AppointmentCode = :aCode
        AND SA.Speciality = :spec
        AND SA.SourceSystem = :sys
");

foreach($combinations as $c){
    $queryUpdateComb->execute([
        ":type"     => $c["Type"],
        ":act"      => $c["Active"],
        ":rCode"    => $c["ResourceCode"],
        ":rName"    => $c["ResourceName"],
        ":aCode"    => $c["AppointmentCode"],
        ":spec"     => $c["Speciality"],
        ":sys"      => $c["SourceSystem"],
    ]);
}

$dbh->commit();

#generate a new csv, updated csv file
$queryGetComb = $dbh->prepare("
    SELECT
        CR.ResourceCode
        ,CR.ResourceName
        ,AC.AppointmentCode
        ,SA.Speciality
        ,SA.SourceSystem
        ,SA.Type
        ,SA.Active
    FROM
        SmsAppointment SA
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = SA.ClinicResourcesSerNum
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = SA.AppointmentCodeId
");
$queryGetComb->execute();

$newCombs = $queryGetComb->fetchAll();

$newFile = preg_replace("/\.csv/","_new.csv",$csvFile);

$return = Csv::writeCsvFromData($newFile,$newCombs);

echo "$return\n";


?>
