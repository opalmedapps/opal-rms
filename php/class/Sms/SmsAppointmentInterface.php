<?php declare(strict_types=1);

namespace Orms\Sms;

use Orms\ApplicationException;
use Orms\DataAccess\SmsAppointmentAccess;

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
    static function getAppointmentsForSms(): array
    {
        return SmsAppointmentAccess::getAppointmentsForSms();
    }

    /**
     * Updates an sms appointment's type and status.
     */
    static function updateSmsAppointment(int $id, int $active, ?string $type): void
    {
        if($active === 1 && $type === NULL) {
            throw new ApplicationException(ApplicationException::INVALID_SMS_APPOINTMENT_STATE,"Sms appointment cannot be active if the type is null");
        }

        SmsAppointmentAccess::updateSmsAppointment($id, $active, $type);
    }

    /**
     * Returns a list of sms appointment types in a speciality group.
     * @return string[]
     */
    static function getSmsAppointmentTypes(?string $specialityCode): ?array
    {
        return SmsAppointmentAccess::getSmsAppointmentTypes($specialityCode);
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
    static function getSmsAppointmentMessages(string $specialityCode, string $type): ?array
    {
        return SmsAppointmentAccess::getSmsAppointmentMessages($specialityCode, $type);
    }

    /**
     * Updates the message text for an sms message.
     */
    static function updateMessageForSms(int $messageId, string $smsMessage): void
    {
        SmsAppointmentAccess::updateMessageForSms($messageId, $smsMessage);
    }
}
