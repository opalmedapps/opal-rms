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
        $resourceIds = self::insertClinicResource($appointmentCode,$clinicCode,$clinicDescription,$specialityGroupCode,$system);

        AppointmentAccess::createOrUpdateAppointment(
            appointmentCodeId:      $resourceIds["appointmentCodeId"],
            clinicId:               $resourceIds["clinicCodeId"],
            creationDate:           $creationDate,
            patientId:              $patient->id,
            scheduledDateTime:      $scheduledDateTime,
            sourceId:               $sourceId,
            status:                 $status,
            system:                 $system,
            visitStatus:            $visitStatus
        );
    }

    /**
     * @return array{
     *   clinicCodeId: int,
     *   appointmentCodeId: int,
     * }
     */
    public static function insertClinicResource(string $appointmentCode,string $clinicCode,string $clinicDescription,string $specialityGroupCode,string $system): array
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

        return [
            "appointmentCodeId" => $appCodeId,
            "clinicCodeId" => $clinicId,
        ];
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
