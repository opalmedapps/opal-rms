<?php declare(strict_types=1);

namespace Orms\DataAccess;

class SmsAppointmentAccess
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
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                SM.SmsAppointmentId,
                SM.Active,
                SM.Type,
                AC.AppointmentCode,
                CR.ResourceCode,
                CR.ResourceName,
                SG.SpecialityGroupName,
                SG.SpecialityGroupCode
            FROM
                SmsAppointment SM
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = SM.AppointmentCodeId
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = SM.ClinicResourcesSerNum
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SM.SpecialityGroupId
        ");
        $query->execute();

        return array_map(fn($x) => [
            "id"                    => (int) $x["SmsAppointmentId"],
            "type"                  => $x["Type"],
            "appointmentCode"       => (string) $x["AppointmentCode"],
            "resourceCode"          => (string) $x["ResourceCode"],
            "resourceDescription"   => (string) $x["ResourceName"],
            "active"                => (int) $x["Active"],
            "speciality"            => (string) $x["SpecialityGroupName"],
            "specialityCode"        => (string) $x["SpecialityGroupCode"],
        ], $query->fetchAll());
    }

    /**
     * Updates an sms appointment's type and status.
     */
    static function updateSmsAppointment(int $id, int $active, ?string $type): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE SmsAppointment
            SET
                Active = :active,
                Type = :type
            WHERE
                SmsAppointmentId = :id
        ")->execute([
            ":active"  => $active,
            ":type"    => $type,
            ":id"      => $id,
        ]);
    }

    /**
     * Returns a list of sms appointment types in a speciality group.
     * @return string[]
     */
    static function getSmsAppointmentTypes(?string $specialityCode = NULL): array
    {
        $specialityFilter = ($specialityCode === NULL) ? NULL : "AND SG.SpecialityGroupCode = :specialityCode";
        $parameters = ($specialityCode === NULL) ? [] : [":specialityCode" => $specialityCode];

        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                SM.Type
            FROM
                SmsMessage SM
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SM.SpecialityGroupId
                $specialityFilter
        ");
        $query->execute($parameters);

        return array_map(fn($x) => (string) $x["Type"], $query->fetchAll());
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
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                SM.Event,
                SM.Language,
                SM.SmsMessageId,
                SM.Message
            FROM
                SmsMessage SM
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SM.SpecialityGroupId
                    AND SG.SpecialityGroupCode = :specialityCode
            WHERE
                SM.Type = :type
            ORDER BY
                SM.Event
        ");
        $query->execute([
            ":specialityCode"   => $specialityCode,
            ":type"             => $type,
        ]);

        return array_map(fn($x) => [
            "event"                 => (string) $x["Event"],
            "language"              => (string) $x["Language"],
            "messageId"             => (int) $x["SmsMessageId"] ,
            "smsMessage"            => (string) $x["Message"],
        ], $query->fetchAll());
    }

    /**
     * Updates the message text for an sms message.
     */
    static function updateMessageForSms(int $messageId, string $smsMessage): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE SmsMessage
            SET
                Message = :message
            WHERE
                SmsMessageId = :messageId
        ")->execute([
            ":message"      => $smsMessage,
            ":messageId"    => $messageId
        ]);
    }
}
