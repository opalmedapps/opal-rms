<?php declare(strict_types = 1);

namespace Orms\Resource;

use Orms\Database;

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

    static function insertSmsAppointment(int $clinicResourceId,int $appointmentCodeId,string $specialityGroup,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO SmsAppointment(ClinicResourcesSerNum,AppointmentCodeId,Speciality,SourceSystem)
            VALUES(:clin,:app,:spec,:sys)
        ")->execute([
            ":clin" => $clinicResourceId,
            ":app"  => $appointmentCodeId,
            ":spec" => $specialityGroup,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

}

?>
