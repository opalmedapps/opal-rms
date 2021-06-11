<?php declare(strict_types = 1);

// script that takes a patient's mrn and site and created an add-on with the first clinic code it can find

require_once __DIR__ ."/../vendor/autoload.php";

use GetOpt\GetOpt;
use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Patient;
use Orms\Appointment\Appointment;
use Respect\Validation\Rules\Date;

//get csv file name from command line arguments and load it
$opts = new GetOpt([
    ["mrn"],
    ["site"]
],[GetOpt::SETTING_DEFAULT_MODE => GetOpt::OPTIONAL_ARGUMENT]);
$opts->process();

$mrn = $opts->getOption("mrn") ?? "";
$site = $opts->getOption("site") ?? "";

$patient = Patient::getPatientByMrn($mrn,$site) ?? throw new Exception("Unknown patient");

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

Appointment::createOrUpdateAppointment(
    patient: $patient,
    appointmentCode: $appointmentCode["AppointmentCode"],
    creationDate: new DateTime(),
    referringMd: NULL,
    clinicCode: $clinicCode["ResourceCode"],
    clinicDescription: $clinicCode["ResourceName"],
    scheduledDateTime: new DateTime(),
    sourceId: "ORMS-$mrn-$site-". (new DateTime())->getTimestamp(),
    sourceStatus: NULL,
    specialityGroupCode: $clinicCode["SpecialityGroupCode"],
    status: "Open",
    system: $clinicCode["SourceSystem"],
);

echo "Created appointment: $clinicCode[ResourceName] | $appointmentCode[AppointmentCode]\n";
