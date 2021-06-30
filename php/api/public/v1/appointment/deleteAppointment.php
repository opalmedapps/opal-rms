<?php declare(strict_types = 1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Appointment\Appointment;
use Orms\DateTime;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Http::sanitizeRequestParams($fields);
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$deletedAppointment = new class(
    appointmentCode:        $fields["appointmentCode"],
    clinics:                $fields["clinics"],
    mrn:                    $fields["mrn"],
    site:                   $fields["site"],
    specialityGroupCode:    $fields["specialityGroupCode"],
    scheduledDatetime:      $fields["scheduledDatetime"],
) {
    public DateTime $scheduledDatetime;
    public string $clinicCode;

    /** @param mixed[] $clinics */
    function __construct(
        public string $appointmentCode,
        array $clinics,
        public string $mrn,
        public string $site,
        public string $specialityGroupCode,
        string $scheduledDatetime
    ) {
        $this->scheduledDatetime = DateTime::createFromFormatN("Y-m-d H:i:s",$scheduledDatetime) ?? throw new Exception("Incorrect datetime format");

        $clinics = array_map(fn($x) => new DeletionClinic(clinicCode: $x["clinicCode"]),$clinics);
        $this->clinicCode = implode("; ",array_map(fn($x) => $x->clinicCode,$clinics));
    }
};

class DeletionClinic
{
    function __construct(
        public string $clinicCode
    ) {}
}

$patient = PatientInterface::getPatientByMrn($deletedAppointment->mrn,$deletedAppointment->site);

if($patient === NULL) {
    Http::generateResponseJsonAndExit(400,error: "Unknown patient");
}

Appointment::deleteSimilarAppointments(
    patient: $patient,
    scheduledDateTime:      $deletedAppointment->scheduledDatetime,
    clinicCode:             $deletedAppointment->clinicCode,
    clinicDescription:      $deletedAppointment->appointmentCode,
    specialityGroupCode:    $deletedAppointment->specialityGroupCode
);

Http::generateResponseJsonAndExit(200);
