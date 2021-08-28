<?php

declare(strict_types=1);

namespace Orms\DataAccess;

class SmsAccess
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
            "appointmentCode"       => $x["AppointmentCode"],
            "resourceCode"          => $x["ResourceCode"],
            "resourceDescription"   => $x["ResourceName"],
            "active"                => (int) $x["Active"],
            "speciality"            => $x["SpecialityGroupName"],
            "specialityCode"        => $x["SpecialityGroupCode"],
        ], $query->fetchAll());
    }

    public static function getSmsAppointmentId(int $clinicResourceId, int $appointmentCodeId): ?int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                SmsAppointmentId
            FROM
                SmsAppointment
            WHERE
                ClinicResourcesSerNum = :clin
                AND AppointmentCodeId = :app
        ");
        $query->execute([
            ":clin" => $clinicResourceId,
            ":app"  => $appointmentCodeId,
        ]);

        $id = (int) ($query->fetchAll()[0]["SmsAppointmentId"] ?? null);
        return $id ?: null;
    }

    public static function insertSmsAppointment(int $clinicResourceId, int $appointmentCodeId, int $specialityGroupId, string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO SmsAppointment(ClinicResourcesSerNum,AppointmentCodeId,SpecialityGroupId,SourceSystem)
            VALUES(:clin,:app,:spec,:sys)
        ")->execute([
            ":clin" => $clinicResourceId,
            ":app"  => $appointmentCodeId,
            ":spec" => $specialityGroupId,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

    /**
     * Updates an sms appointment's type and status.
     */
    public static function updateSmsAppointment(int $id, int $active, ?string $type): void
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
    public static function getSmsAppointmentTypes(?string $specialityCode = null): array
    {
        $specialityFilter = ($specialityCode === null) ? null : "AND SG.SpecialityGroupCode = :specialityCode";
        $parameters = ($specialityCode === null) ? [] : [":specialityCode" => $specialityCode];

        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                SM.Type
            FROM
                SmsMessage SM
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SM.SpecialityGroupId
                    $specialityFilter
        ");
        $query->execute($parameters);

        return array_map(fn($x) => $x["Type"], $query->fetchAll());
    }

    /**
     * Returns a list of sms messages.
     *  @return list<array{
     *   event: string,
     *   language: string,
     *   message: string,
     *   specialityGroupCode: string,
     *   specialityGroupId: int,
     *   smsMessageId: int,
     *   type: string,
     * }>
     */
    public static function getSmsAppointmentMessages(): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                SG.SpecialityGroupCode,
                SM.SpecialityGroupId,
                SM.SmsMessageId,
                SM.Type,
                SM.Event,
                SM.Language,
                SM.Message
            FROM
                SmsMessage SM
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = SM.SpecialityGroupId
            ORDER BY
                SM.SpecialityGroupId,
                SM.Type,
                SM.Event,
                SM.Language
        ");
        $query->execute();

        return array_map(fn($x) => [
            "event"                 => $x["Event"],
            "language"              => $x["Language"],
            "message"               => $x["Message"],
            "specialityGroupCode"   => $x["SpecialityGroupCode"],
            "specialityGroupId"     => (int) $x["SpecialityGroupId"],
            "smsMessageId"          => (int) $x["SmsMessageId"],
            "type"                  => $x["Type"],
        ], $query->fetchAll());
    }

    /**
     * Updates the message text for an sms message.
     */
    public static function updateMessageForSms(int $messageId, string $smsMessage): void
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
