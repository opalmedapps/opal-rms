<?php declare(strict_types = 1);

namespace Orms\Appointment;

use Orms\Patient\Patient;
use Orms\DateTime;
use Orms\Database;
use Orms\Appointment\Internal\ClinicResource;
use Orms\Appointment\Internal\AppointmentCode;
use Orms\Appointment\Internal\SmsAppointment;
use Orms\System\Mail;
use Orms\Hospital\OIE\Export;

class Appointment
{
    static function createOrUpdateAppointment(
        Patient $patient,
        string $appointmentCode,
        DateTime $creationDate,
        ?string $referringMd,
        string $clinicCode,
        string $clinicDescription,
        DateTime $scheduledDateTime,
        string $sourceId,
        ?string $sourceStatus,
        string $specialityGroup,
        string $status,
        string $system,
    ): void
    {
        //get the necessary ids that are attached to the appointment
        $clinicId = ClinicResource::getClinicResourceId($clinicCode,$specialityGroup);
        if($clinicId === NULL) {
            $clinicId = ClinicResource::insertClinicResource($clinicCode,$clinicDescription,$specialityGroup,$system);
        }
        else {
            ClinicResource::updateClinicResource($clinicId,$clinicDescription);
        }


        $appCodeId = AppointmentCode::getAppointmentCodeId($appointmentCode,$specialityGroup);
        if($appCodeId === NULL) $appCodeId = AppointmentCode::insertAppointmentCode($appointmentCode,$specialityGroup,$system);

        //check if an sms entry for the resource combinations exists and create if it doesn't
        $smsAppointmentId = SmsAppointment::getSmsAppointmentId($clinicId,$appCodeId);

        if($smsAppointmentId === NULL)
        {
            //add-ons are created from existing appointment types
            //however, there are restrictions on the frontend, allowing the creation of invalid add-ons
            //so we disable inserting sms appointments for add-ons
            if($system !== "InstantAddOn")
            {
                SmsAppointment::insertSmsAppointment($clinicId,$appCodeId,$specialityGroup,$system);

                Mail::sendEmail("ORMS - New appointment type detected",
                    "New appointment type detected: $clinicDescription ($clinicCode) with $appointmentCode in the $specialityGroup speciality group from system $system."
                );
            }
        }

        Database::getOrmsConnection()->prepare("
            INSERT INTO MediVisitAppointmentList
            SET
                PatientSerNum          = :patSer,
                ClinicResourcesSerNum  = :clinSer,
                ScheduledDateTime      = :schDateTime,
                ScheduledDate          = :schDate,
                ScheduledTime          = :schTime,
                AppointmentCodeId      = :appCodeId,
                AppointId              = :appId,
                AppointSys             = :appSys,
                Status                 = :status,
                MedivisitStatus        = :mvStatus,
                CreationDate           = :creDate,
                ReferringPhysician     = :refPhys,
                LastUpdatedUserIP      = :callIP
            ON DUPLICATE KEY UPDATE
                PatientSerNum           = VALUES(PatientSerNum),
                ClinicResourcesSerNum   = VALUES(ClinicResourcesSerNum),
                ScheduledDateTime       = VALUES(ScheduledDateTime),
                ScheduledDate           = VALUES(ScheduledDate),
                ScheduledTime           = VALUES(ScheduledTime),
                AppointmentCodeId       = VALUES(AppointmentCodeId),
                AppointId               = VALUES(AppointId),
                AppointSys              = VALUES(AppointSys),
                Status                  = CASE WHEN Status = 'Completed' THEN 'Completed' ELSE VALUES(Status) END,
                MedivisitStatus         = VALUES(MedivisitStatus),
                CreationDate            = VALUES(CreationDate),
                ReferringPhysician      = VALUES(ReferringPhysician),
                LastUpdatedUserIP       = VALUES(LastUpdatedUserIP)
        ")->execute([
            ":patSer"       => $patient->id,
            ":clinSer"      => $clinicId,
            ":schDateTime"  => $scheduledDateTime->format("Y-m-d H:i:s"),
            ":schDate"      => $scheduledDateTime->format("Y-m-d"),
            ":schTime"      => $scheduledDateTime->format("H:i:s"),
            ":appCodeId"    => $appCodeId,
            ":appId"        => $sourceId,
            ":appSys"       => $system,
            ":status"       => $status,
            ":mvStatus"     => $sourceStatus,
            ":creDate"      => $creationDate->format(("Y-m-d H:i:s")),
            ":refPhys"      => $referringMd,
            ":callIP"       => empty($_SERVER["REMOTE_ADDR"]) ? gethostname() : $_SERVER["REMOTE_ADDR"]
        ]);
    }

    //deletes all similar appointments in the database
    //similar is defined as having the same appointment resources, and being scheduled at the same time (for the same patient)
    static function deleteSimilarAppointments(Patient $patient,DateTime $scheduledDateTime,string $clinicCode,string $clinicDescription,string $specialityGroup): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND CR.ResourceCode = :res
                AND CR.Speciality = :spec
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                AND AC.AppointmentCode = :appCode
                AND AC.Speciality = :spec
            SET
                Status = 'Deleted'
            WHERE
                PatientSerNum = :patSer
                AND ScheduledDateTime = :schDateTime
        ")->execute([
            ":patSer"       => $patient->id,
            ":schDateTime"  => $scheduledDateTime->format("Y-m-d H:i:s"),
            ":res"          => $clinicCode,
            ":appCode"      => $clinicDescription,
            ":spec"         => $specialityGroup
        ]);
    }

    static function completeAppointment(int $appointmentId): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE MediVisitAppointmentList
            SET
                Status = 'Completed'
            WHERE
                AppointmentSerNum = ?
        ")->execute([$appointmentId]);

        //retrieve necessary fields to export the appointment completion to the OIE
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                AppointId
                ,AppointSys
            FROM
                MediVisitAppointmentList
            WHERE
                AppointmentSerNum = ?
        ");
        $query->execute([$appointmentId]);
        $app = $query->fetchAll()[0];

        Export::exportAppointmentCompletion($app["AppointId"],$app["AppointSys"]);
    }

}
