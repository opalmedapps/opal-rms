<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../vendor/autoload.php";

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

use Orms\Database;

$dbh = Database::getOrmsConnection();

//appointment changes
AppointmentSourceSystem::removeSourceSystemConstraint($dbh);
AppointmentSourceSystem::createSourceSystemKey($dbh);
AppointmentCodes::extendCodeLength($dbh);
AppointmentCodes::addDisplayColumn($dbh);
AppointmentCodes::correctSourceSystem($dbh);

try {
    $dbh->beginTransaction();
    AppointmentSourceSystem::removeAriaPrefixFromSourceId($dbh);
    AppointmentCodes::deleteAddOns($dbh);
    AppointmentCodes::fixZoomAppointments($dbh);
    AppointmentCodes::fixAriaAppointmentCodes($dbh,__DIR__."/appointment/misnamed_appointment_codes.csv");
    $dbh->commit();
}
catch(Exception $e) {
    $dbh->rollBack();
    throw $e;
}

AppointmentForeignKeys::updatePatientTableLink($dbh);
AppointmentForeignKeys::updateResourceCodeLinks($dbh);

// profile changes
Profile::removeLegacyProfileColumns($dbh);

//room changes
Rooms::extendRoomNameLength($dbh);

//patient measurement changes
PatientMeasurementTable::linkPatientMeasurementTable($dbh);
PatientMeasurementTable::updatePatientIdColumn($dbh);

//patient changes
PatientIdentifiers::createHospitalTable($dbh);
PatientIdentifiers::createInsuranceTable($dbh);
PatientIdentifiers::createPatientHospitalIdentifierTable($dbh);
PatientIdentifiers::createPatientInsuranceIdentifierTable($dbh);

PatientTable::addDateOfBirthColumn($dbh);
PatientTable::updateSmsSignupDate($dbh);

PatientTable::fixSmsDates($dbh);
$unknownPatients = PatientTable::migratePatientDemographics($dbh);
echo "$unknownPatients not matched in ADT\n";

$userInput = NULL;
while($userInput !== "CONTINUE")
{
    echo "Please verify the unknown patients and then type 'CONTINUE'\n";
    $userInput = readline();
}

$unknownPatients = PatientTable::migratePatientDemographics($dbh,FALSE);
echo "$unknownPatients not matched in ADT\n";

PatientTable::removeDeprecatedPatientColumns($dbh);

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
