<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

require_once __DIR__."/removeLegacy/updateProfiles.php";
require_once __DIR__."/appointment/updateAppointmentSourceSystem.php";
require_once __DIR__."/appointment/updateCodes.php";
require_once __DIR__."/patient/updatePatientTable.php";

use Orms\Database;

$dbh = Database::getOrmsConnection();

//table structure changes
removeLegacyProfileColumns($dbh);
removeSourceSystemConstraint($dbh);
createSourceSystemKey($dbh);
addDateOfBirthColumn($dbh);

//data changes
$dbh->beginTransaction();

removeAriaPrefixFromSourceId($dbh);
fixAriaAppointmentCodes($dbh,__DIR__."/appointment/misnamed_appointment_codes.csv");

$dbh->commit();
