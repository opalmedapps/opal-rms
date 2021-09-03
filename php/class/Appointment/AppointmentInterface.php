<?php

declare(strict_types=1);

namespace Orms\Appointment;

use Exception;
use Orms\DataAccess\AppointmentAccess;
use Orms\DateTime;
use Orms\External\OIE\Export;
use Orms\Hospital\HospitalInterface;
use Orms\Patient\Model\Patient;
use Orms\Sms\SmsAppointmentInterface;
use Orms\System\Mail;

class AppointmentInterface
{
    public static function createOrUpdateAppointment(
        Patient $patient,
        string $appointmentCode,
        DateTime $creationDate,
        string $clinicCode,
        string $clinicDescription,
        DateTime $scheduledDateTime,
        string $sourceId,
        string $specialityGroupCode,
        string $status,
        string $system,
        ?string $visitStatus,
    ): void
    {
        //get the necessary ids that are attached to the appointment
        $specialityGroupId = HospitalInterface::getSpecialityGroupId($specialityGroupCode) ?? throw new Exception("Unknown speciality group code $specialityGroupCode");

        $clinicId = AppointmentAccess::getClinicResourceId($clinicCode,$specialityGroupId);
        if($clinicId === null) {
            $clinicId = AppointmentAccess::insertClinicResource($clinicCode, $clinicDescription, $specialityGroupId, $system);
        }
        else {
            AppointmentAccess::updateClinicResource($clinicId, $clinicDescription);
        }

        $appCodeId = AppointmentAccess::getAppointmentCodeId($appointmentCode, $specialityGroupId);
        if($appCodeId === null) {
            $appCodeId = AppointmentAccess::insertAppointmentCode($appointmentCode, $specialityGroupId, $system);
        }

        //check if an sms entry for the resource combinations exists and create if it doesn't
        $smsAppointmentId = SmsAppointmentInterface::getSmsAppointmentId($clinicId, $appCodeId);

        if($smsAppointmentId === null)
        {
            SmsAppointmentInterface::insertSmsAppointment($clinicId, $appCodeId, $specialityGroupId, $system);

            Mail::sendEmail(
                "ORMS - New appointment type detected",
                "New appointment type detected: $clinicDescription ($clinicCode) with $appointmentCode in the $specialityGroupCode speciality group from system $system."
            );
        }

        AppointmentAccess::createOrUpdateAppointment(
            appointmentCodeId:      $appCodeId,
            clinicId:               $clinicId,
            creationDate:           $creationDate,
            patientId:              $patient->id,
            scheduledDateTime:      $scheduledDateTime,
            sourceId:               $sourceId,
            status:                 $status,
            system:                 $system,
            visitStatus:            $visitStatus
        );
    }

    //deletes all similar appointments in the database
    //similar is defined as having the same appointment resources, and being scheduled at the same time (for the same patient)
    public static function deleteSimilarAppointments(Patient $patient, DateTime $scheduledDateTime, string $clinicCode, string $clinicDescription, string $specialityGroupCode): void
    {
        $specialityGroupId = HospitalInterface::getSpecialityGroupId($specialityGroupCode) ?? throw new Exception("Unknown speciality group code $specialityGroupCode");
        AppointmentAccess::deleteSimilarAppointments($patient->id,$scheduledDateTime,$clinicCode,$clinicDescription,$specialityGroupId);
    }

    public static function completeAppointment(int $appointmentId): void
    {
        AppointmentAccess::completeAppointment($appointmentId);

        //retrieve necessary fields to export the appointment completion to the OIE
        $app = AppointmentAccess::getAppointmentDetails($appointmentId) ?? throw new Exception("Unable to complete appointment $appointmentId");
        Export::exportAppointmentCompletion($app["sourceId"], $app["sourceSystem"]);
    }

    public static function updateAppointmentReminderFlag(int $appointmentId): void
    {
        AppointmentAccess::updateAppointmentReminderFlag($appointmentId);
    }

    /**
     *
     * @return list<array{
     *   description: string,
     *   code: string
     * }>
     */
    public static function getClinicCodes(int $specialityId): array
    {
        return AppointmentAccess::getClinicCodes($specialityId);
    }

    /**
     *
     * @return list<array{
     *  AppointmentCode: string
     * }>
     */
    public static function getAppointmentCodes(int $specialityGroupId): array
    {
        return AppointmentAccess::getAppointmentCodes($specialityGroupId);
    }

    /**
     *
     * @return list<array{
     *  appointmentCode: string,
     *  appointmentId: int,
     *  clinicCode: string,
     *  clinicDescription: string,
     *  patientId: int,
     *  scheduledDatetime: DateTime,
     *  smsActive: bool,
     *  smsReminderSent: bool,
     *  smsType: ?string,
     *  sourceId: string,
     *  sourceSystem: string,
     *  specialityGroupId: int,
     * }>
     */
    public static function getOpenAppointments(DateTime $startDatetime,Datetime $endDatetime,Patient $patient = null): array
    {
        return AppointmentAccess::getOpenAppointments($startDatetime,$endDatetime,$patient?->id);
    }

}
