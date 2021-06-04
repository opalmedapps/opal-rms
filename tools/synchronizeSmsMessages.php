<?php declare(strict_types = 1);

/*
    This script takes in an input csv file and updates the SmsMessages db table to match it's contents. It also exports the updated table to a csv file (to account for new codes that have been added).
*/

require_once __DIR__ ."/../vendor/autoload.php";

use GetOpt\GetOpt;

use Orms\Database;
use Orms\Util\Csv;

//get csv file name from command line arguments and load it
$opts = new GetOpt([
    ["file"],
],[GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$csvFile = $opts->getOption("file") ?? "";

$combinations = Csv::loadCsvFromFile($csvFile);

$dbh = Database::getOrmsConnection();
$dbh->beginTransaction();

#update the db
$queryUpdateComb = $dbh->prepare("
    UPDATE SmsAppointment SA
    INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = SA.ClinicResourcesSerNum
        AND CR.ResourceCode = :rCode
        AND CR.ResourceName = :rName
    INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = SA.AppointmentCodeId
        AND AC.AppointmentCode = :aCode
    INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SA.SpecialityGroupId
        AND SG.SpecialityGroupName = :spec
    SET
        SA.Type = :type,
        SA.Active = :act
    WHERE
        SA.SourceSystem = :sys
");

foreach($combinations as $c){
    $queryUpdateComb->execute([
        ":type"     => $c["Type"],
        ":act"      => $c["Active"],
        ":rCode"    => $c["ResourceCode"],
        ":rName"    => $c["ResourceName"],
        ":aCode"    => $c["AppointmentCode"],
        ":spec"     => $c["SpecialityGroupName"],
        ":sys"      => $c["SourceSystem"],
    ]);
}

$dbh->commit();

#generate a new csv, updated csv file
$queryGetComb = $dbh->prepare("
    SELECT
        CR.ResourceCode,
        CR.ResourceName,
        AC.AppointmentCode,
        SG.SpecialityGroupName,
        SA.SourceSystem,
        SA.Type,
        SA.Active
    FROM
        SmsAppointment SA
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = SA.ClinicResourcesSerNum
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = SA.AppointmentCodeId
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SA.SpecialityGroupId
");
$queryGetComb->execute();

$newCombs = $queryGetComb->fetchAll();

$newFile = preg_replace("/\.csv/","_new.csv",$csvFile);

$return = Csv::writeCsvFromData($newFile,$newCombs);

echo "$return\n";
