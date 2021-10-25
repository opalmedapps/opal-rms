<?php

declare(strict_types=1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

require_once __DIR__."/appointment/updateAppointmentStatus.php";
require_once __DIR__."/appointment/addForeignKeys.php";
require_once __DIR__."/appointment/updateAppointmentSourceSystem.php";
require_once __DIR__."/appointment/updateCodes.php";
require_once __DIR__."/patient/addIdentifiers.php";
require_once __DIR__."/patient/updatePatientTable.php";
require_once __DIR__."/patient/updatePatientMeasurementTable.php";
require_once __DIR__."/profile/updateProfiles.php";
require_once __DIR__."/speciality/createSpecialityGroup.php";
require_once __DIR__."/speciality/updateClinicHubs.php";
require_once __DIR__."/room/updateRooms.php";
require_once __DIR__."/cleanup/deprecatedTables.php";

use Orms\DataAccess\Database;

$dbh = Database::getOrmsConnection();
$dbhLog = Database::getLogsConnection();

echo "appointment changes\n";
AppointmentStatus::removeInProgressStatus($dbh);
AppointmentStatus::removeDeprecatedSourceStatuses($dbh);
AppointmentSourceSystem::removeSourceSystemConstraint($dbh);
AppointmentSourceSystem::createSourceSystemKey($dbh);
AppointmentCodes::extendCodeLength($dbh);
AppointmentCodes::addDisplayColumn($dbh);
AppointmentCodes::correctSourceSystem($dbh);
AppointmentCodes::updateCreationDate($dbh);

try {
    $dbh->beginTransaction();
    AppointmentSourceSystem::removeAriaPrefixFromSourceId($dbh);
    AppointmentCodes::deleteAddOns($dbh);
    AppointmentCodes::fixZoomAppointments($dbh);
    AppointmentCodes::fixAriaAppointmentCodes($dbh, __DIR__."/appointment/misnamed_appointment_codes.csv");
    $dbh->commit();
}
catch(Exception $e) {
    $dbh->rollBack();
    throw $e;
}

AppointmentForeignKeys::updatePatientTableLink($dbh);
AppointmentForeignKeys::updateResourceCodeLinks($dbh);

echo "profile changes\n";
Profile::removeLegacyProfileColumns($dbh);
Profile::removeTreatmentVenues($dbh);
Profile::removeStoredProcedures($dbh);

echo "room changes\n";
Rooms::extendRoomNameLength($dbh);

echo "patient measurement changes\n";
PatientMeasurementTable::linkPatientMeasurementTable($dbh);
PatientMeasurementTable::updatePatientIdColumn($dbh);

echo "patient changes\n";
PatientIdentifiers::createHospitalTable($dbh);
PatientIdentifiers::createInsuranceTable($dbh);
PatientIdentifiers::createPatientHospitalIdentifierTable($dbh);
PatientIdentifiers::createPatientInsuranceIdentifierTable($dbh);

PatientTable::addDateOfBirthColumn($dbh);
PatientTable::addSexColumn($dbh);
PatientTable::updateSmsSignupDate($dbh);

PatientTable::fixSmsDates($dbh);
$unknownPatients = PatientTable::migratePatientDemographics($dbh);
echo "$unknownPatients not matched in ADT\n";

$userInput = null;
while($userInput !== "CONTINUE")
{
    echo "Please verify the unknown patients and then type 'CONTINUE'\n";
    $userInput = readline();
}

$unknownPatients = PatientTable::migratePatientDemographics($dbh, false);
echo "$unknownPatients not matched in ADT\n";

PatientTable::removeDeprecatedPatientColumns($dbh);

echo "speciality changes\n";
SpecialityGroup::createSpecialityGroupTable($dbh);
SpecialityGroup::linkAppointmentCodeTable($dbh);
SpecialityGroup::linkClinicResourcesTable($dbh);
SpecialityGroup::linkProfileTable($dbh);
SpecialityGroup::linkSmsAppointmentTable($dbh);
SpecialityGroup::linkSmsMessageTable($dbh);

echo "clinic hub changes\n";
ClinicHubs::recreateClinicHubTable($dbh);
ClinicHubs::linkExamRoomTable($dbh);
ClinicHubs::linkIntermediateVenueTable($dbh);
ClinicHubs::unlinkProfileTable($dbh);

echo "remove deprecated tables\n";
DeprecatedTables::removeDoctorSchedule($dbh);
DeprecatedTables::removeVenue($dbh);
DeprecatedTables::removeCheckoutEvent($dbh);
DeprecatedTables::removeScheduler($dbh);
DeprecatedTables::removeSmsLogs($dbh);
DeprecatedTables::removeKioskLog($dbhLog);
DeprecatedTables::removeAppointmentLogs($dbhLog);

echo "Migration done\n";
