<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

require_once __DIR__."/../";

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

//clinic hub changes
ClinicHubs::recreateClinicHubTable($dbh);
ClinicHubs::linkExamRoomTable($dbh);
ClinicHubs::linkIntermediateVenueTable($dbh);
ClinicHubs::linkProfileTable($dbh);

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
