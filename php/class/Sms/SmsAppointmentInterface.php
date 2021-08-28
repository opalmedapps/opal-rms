<?php

declare(strict_types=1);

namespace Orms\Sms;

use Orms\ApplicationException;
use Orms\DataAccess\SmsAccess;

class SmsAppointmentInterface
{
    /**
     * Returns a list of all sms appointments in the system.
     * @return list<array{
     *      id: int,
     *      type: ?string,
     *      appointmentCode: string,
     *      resourceCode: string,
     *      resourceDescription: string,
     *      active: int,
     *      speciality: string,
     * }>
     */
    public static function getAppointmentsForSms(): array
    {
        return SmsAccess::getAppointmentsForSms();
    }

    public static function getSmsAppointmentId(int $clinicResourceId, int $appointmentCodeId): ?int
    {
        return SmsAccess::getSmsAppointmentId($clinicResourceId,$appointmentCodeId);
    }

    public static function insertSmsAppointment(int $clinicResourceId, int $appointmentCodeId, int $specialityGroupId, string $system): int
    {
        return SmsAccess::insertSmsAppointment($clinicResourceId,$appointmentCodeId,$specialityGroupId,$system);
    }

    /**
     * Updates an sms appointment's type and status.
     */
    public static function updateSmsAppointment(int $id, int $active, ?string $type): void
    {
        if($active === 1 && $type === null) {
            throw new ApplicationException(ApplicationException::INVALID_SMS_APPOINTMENT_STATE, "Sms appointment cannot be active if the type is null");
        }

        if(in_array($type,self::getSmsAppointmentTypes()) === false) {
            throw new ApplicationException(ApplicationException::INVALID_SMS_APPOINTMENT_TYPE, "Type $type doesn't exist for sms appointments");
        }

        SmsAccess::updateSmsAppointment($id, $active, $type);
    }

    /**
     * Returns a list of sms appointment types in a speciality group.
     * @return string[]
     */
    public static function getSmsAppointmentTypes(?string $specialityCode = null): array
    {
        return SmsAccess::getSmsAppointmentTypes($specialityCode);
    }

    /**
     * Returns a list of sms messages.
     *  @return list<array{
     *      event: string,
     *      language: string,
     *      messageId: int,
     *      smsMessage: string
     * }>
     */
    public static function getSmsAppointmentMessages(string $specialityCode, string $type): array
    {
        $messages = SmsAccess::getSmsAppointmentMessages();
        $messages = array_values(array_filter($messages,fn($x) => $x["specialityGroupCode"] === $specialityCode && $x["type"] === $type));

        return array_map(fn($x) => [
            "event"         => $x["event"],
            "language"      => $x["language"],
            "messageId"     => $x["smsMessageId"],
            "smsMessage"    => $x["message"],
        ],$messages);
    }

    /**
     * Updates the message text for an sms message.
     */
    public static function updateMessageForSms(int $messageId, string $smsMessage): void
    {
        SmsAccess::updateMessageForSms($messageId, $smsMessage);
    }
}
