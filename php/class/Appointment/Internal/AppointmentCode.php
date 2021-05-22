<?php declare(strict_types = 1);

namespace Orms\Appointment\Internal;

use Orms\Database;

class AppointmentCode
{
    static function getAppointmentCodeId(string $code,string $specialityGroup): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                AppointmentCodeId
            FROM
                AppointmentCode
            WHERE
                AppointmentCode = :code
                AND Speciality = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroup,
        ]);

        $id = (int) ($query->fetchAll()[0]["AppointmentCodeId"] ?? NULL);
        return $id ?: NULL;
    }

    static function insertAppointmentCode(string $code,string $specialityGroup,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO AppointmentCode(AppointmentCode,Speciality,SourceSystem)
            VALUES(:code,:spec,:sys)
        ")->execute([
            ":code" => $code,
            ":spec" => $specialityGroup,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

}
