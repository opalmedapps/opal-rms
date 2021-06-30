<?php declare(strict_types = 1);

namespace Orms\Appointment\Internal;

use Orms\DataAccess\Database;

class SmsAppointment
{
    static function getSmsAppointmentId(int $clinicResourceId,int $appointmentCodeId): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
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

        $id = (int) ($query->fetchAll()[0]["SmsAppointmentId"] ?? NULL);
        return $id ?: NULL;
    }

    static function insertSmsAppointment(int $clinicResourceId,int $appointmentCodeId,int $specialityGroupId,string $system): int
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

}
