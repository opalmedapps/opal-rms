<?php

declare(strict_types=1);

// script that takes a patient's mrn and site and created an add-on with the first clinic code it can find

require_once __DIR__ ."/../../vendor/autoload.php";

use GetOpt\GetOpt;
use Orms\Appointment\AppointmentInterface;
use Orms\DataAccess\Database;
use Orms\DateTime;
// use Orms\Hospital\HospitalInterface;
use Orms\Patient\PatientInterface;

//load command line arguments
$opts = new GetOpt([
    ["mrn"],
    ["site"]
], [GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$mrn = $opts->getOption("mrn") ?? "";
$site = $opts->getOption("site") ?? "";

$patient = PatientInterface::getPatientByMrn($mrn, $site) ?? throw new Exception("Unknown patient");

// $speciality = HospitalInterface::getSpecialityGroups()[0];
// $clinic = AppointmentInterface::getClinicCodes($speciality["specialityGroupId"])[0] ?? throw new Exception("No clinic code available");
// $appointmentCode = AppointmentInterface::getAppointmentCodes($speciality["specialityGroupId"])[0] ?? throw new Exception("No appointment code available");

$dbh = Database::getOrmsConnection();

$queryClinicCode = $dbh->prepare("
    SELECT
        CR.ResourceCode,
        CR.ResourceName,
        CR.SourceSystem,
        CR.SpecialityGroupId,
        SG.SpecialityGroupCode
    FROM
        ClinicResources CR
        INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
    ORDER BY CR.ResourceName
    LIMIT 1
");
$queryClinicCode->execute();
$clinicCode = $queryClinicCode->fetchAll()[0] ?? throw new Exception("No clinic code available");

$queryAppointmentCode = $dbh->prepare("
    SELECT
        AppointmentCode
    FROM
        AppointmentCode
    WHERE
        SpecialityGroupId = ?
        AND SourceSystem = ?
    ORDER BY AppointmentCode
    LIMIT 1
");
$queryAppointmentCode->execute([$clinicCode["SpecialityGroupId"],$clinicCode["SourceSystem"]]);

$appointmentCode = $queryAppointmentCode->fetchAll()[0] ?? throw new Exception("No appointment code available");

AppointmentInterface::createOrUpdateAppointment(
    patient: $patient,
    appointmentCode: $appointmentCode["AppointmentCode"],
    creationDate: new DateTime(),
    clinicCode: $clinicCode["ResourceCode"],
    clinicDescription: $clinicCode["ResourceName"],
    scheduledDateTime: new DateTime(),
    sourceId: "ORMS-$mrn-$site-". (new DateTime())->getTimestamp(),
    specialityGroupCode: $clinicCode["SpecialityGroupCode"],
    status: "Open",
    system: $clinicCode["SourceSystem"],
    visitStatus: null,
);

echo "Created appointment: $clinicCode[ResourceName] | $appointmentCode[AppointmentCode]\n";
