<?php

declare(strict_types=1);

// inserts an appointment into db, updates that appointment if there's already an appointment with the same id

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\DateTime;
use Orms\Http;
use Orms\Patient\PatientInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Http::sanitizeRequestParams($fields);
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$appointment = new class(
    appointmentCode:        $fields["appointmentCode"],
    clinics:                $fields["clinics"],
    creationDatetime:       $fields["creationDatetime"],
    mrn:                    $fields["mrn"],
    scheduledDatetime:      $fields["scheduledDatetime"],
    site:                   $fields["site"],
    sourceId:               $fields["sourceId"],
    sourceSystem:           $fields["sourceSystem"],
    specialityGroupCode:    $fields["specialityGroupCode"],
    status:                 $fields["status"],
    visitStatus:            $fields["visitStatus"] ?? null
) {
    public DateTime $scheduledDatetime;
    public DateTime $creationDatetime;
    public string $clinicCode;
    public string $clinicDescription;

    /** @param mixed[] $clinics */
    public function __construct(
        public string $appointmentCode,
        //public string $appointmentCodeDescription,
        array $clinics,
        string $creationDatetime,
        public string $mrn,
        string $scheduledDatetime,
        public string $site,
        public string $sourceId,
        public string $sourceSystem,
        public string $specialityGroupCode,
        public string $status,
        public ?string $visitStatus,
    ) {
        $this->scheduledDatetime = DateTime::createFromFormatN("Y-m-d H:i:s", $scheduledDatetime) ?? throw new Exception("Incorrect datetime format");
        $this->creationDatetime = DateTime::createFromFormatN("Y-m-d H:i:s", $creationDatetime) ?? throw new Exception("Incorrect datetime format");

        $clinics = array_map(fn($x) => new AppClinic(clinicCode: $x["clinicCode"], clinicDescription: $x["clinicDescription"]), $clinics);
        $this->clinicCode = implode("; ", array_map(fn($x) => $x->clinicCode, $clinics));
        $this->clinicDescription = implode("; ", array_map(fn($x) => $x->clinicDescription, $clinics));
    }
};

class AppClinic
{
    public function __construct(
        public string $clinicCode,
        public string $clinicDescription
    ) {}
}

$patient = PatientInterface::getPatientByMrn($appointment->mrn, $appointment->site);

if($patient === null) {
    Http::generateResponseJsonAndExit(400, error: "Patient not found");
}

AppointmentInterface::createOrUpdateAppointment(
    patient:                $patient,
    appointmentCode:        $appointment->appointmentCode,
    creationDate:           $appointment->creationDatetime,
    clinicCode:             $appointment->clinicCode,
    clinicDescription:      $appointment->clinicDescription,
    scheduledDateTime:      $appointment->scheduledDatetime,
    sourceId:               $appointment->sourceId,
    specialityGroupCode:    $appointment->specialityGroupCode,
    status:                 $appointment->status,
    system:                 $appointment->sourceSystem,
    visitStatus:            $appointment->visitStatus
);

Http::generateResponseJsonAndExit(200);
