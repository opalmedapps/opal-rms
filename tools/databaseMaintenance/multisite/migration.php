<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

require_once __DIR__."/appointment/addForeignKeys.php";
require_once __DIR__."/appointment/updateAppointmentSourceSystem.php";
require_once __DIR__."/appointment/updateCodes.php";
require_once __DIR__."/patient/addIdentifiers.php";
require_once __DIR__."/patient/updatePatientTable.php";
require_once __DIR__."/profile/updateProfiles.php";
require_once __DIR__."/speciality/createSpecialityGroup.php";
require_once __DIR__."/speciality/updateClinicHubs.php";
require_once __DIR__."/room/updateRooms.php";

use Orms\Database;

$dbh = Database::getOrmsConnection();

//appointment changes
AppointmentForeignKeys::updateResourceCodeLinks($dbh);
AppointmentSourceSystem::removeSourceSystemConstraint($dbh);
AppointmentSourceSystem::createSourceSystemKey($dbh);

$dbh->beginTransaction();
AppointmentSourceSystem::removeAriaPrefixFromSourceId($dbh);
AppointmentCodes::fixAriaAppointmentCodes($dbh,__DIR__."/appointment/misnamed_appointment_codes.csv");
$dbh->commit();

//profile changes
Profile::removeLegacyProfileColumns($dbh);

//room changes
Rooms::extendRoomNameLength($dbh);

//speciality changes
SpecialityGroup::createSpecialityGroupTable($dbh);
SpecialityGroup::linkAppointmentCodeTable($dbh);
SpecialityGroup::linkClinicResourcesTable($dbh);
SpecialityGroup::linkProfileTable($dbh);
SpecialityGroup::linkSmsAppointmentTable($dbh);
SpecialityGroup::linkSmsMessageTable($dbh);

//clinic hub changes
ClinicHubs::recreateClinicHubTable($dbh);
ClinicHubs::linkExamRoomTable($dbh);
ClinicHubs::linkIntermediateVenueTable($dbh);
ClinicHubs::unlinkProfileTable($dbh);

//patient changes
PatientIdentifiers::createHospitalTable($dbh);
PatientIdentifiers::createInsuranceTable($dbh);
PatientIdentifiers::createPatientHospitalIdentifierTable($dbh);
PatientIdentifiers::createPatientInsuranceIdentifierTable($dbh);

PatientTable::addDateOfBirthColumn($dbh);
PatientTable::updateSmsSignupDate($dbh);

$dbh->beginTransaction();
PatientTable::fixSmsDates($dbh);
PatientTable::migratePatientDemographics($dbh);
$dbh->commit();

PatientTable::removeDeprecatedPatientColumns($dbh);
