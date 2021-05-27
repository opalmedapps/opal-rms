<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

require_once __DIR__."/hospital/addTables.php";
require_once __DIR__."/removeLegacy/updateProfiles.php";
require_once __DIR__."/appointment/updateAppointmentSourceSystem.php";
require_once __DIR__."/appointment/updateCodes.php";
require_once __DIR__."/appointment/addForeignKeys.php";
require_once __DIR__."/patient/updatePatientTable.php";
require_once __DIR__."/room/updateRooms.php";

use Orms\Database;

$dbh = Database::getOrmsConnection();

//non patient table structure changes
createHospitalTable($dbh);
createInsuranceTable($dbh);
createPatientHospitalIdentifierTable($dbh);
createPatientInsuranceIdentifierTable($dbh);

updateResourceCodeLinks($dbh);
removeLegacyProfileColumns($dbh);
removeSourceSystemConstraint($dbh);
createSourceSystemKey($dbh);
extendRoomNameLength($dbh);


//non-patient data changes
$dbh->beginTransaction();

removeAriaPrefixFromSourceId($dbh);
fixAriaAppointmentCodes($dbh,__DIR__."/appointment/misnamed_appointment_codes.csv");

$dbh->commit();

//patient data and structure changes
addDateOfBirthColumn($dbh);
updateSmsSignupDate($dbh);

$dbh->beginTransaction();

fixSmsDates($dbh);
migratePatientDemographics($dbh);

$dbh->commit();

removeDeprecatedColumns($dbh);
