<?php declare(strict_types = 1);

namespace Orms\Appointment\Internal;

use Orms\DataAccess\Database;

class AppointmentCode
{
    static function getAppointmentCodeId(string $code,int $specialityGroupId): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                AppointmentCodeId
            FROM
                AppointmentCode
            WHERE
                AppointmentCode = :code
                AND SpecialityGroupId = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
        ]);

        $id = (int) ($query->fetchAll()[0]["AppointmentCodeId"] ?? NULL);
        return $id ?: NULL;
    }

    static function insertAppointmentCode(string $code,int $specialityGroupId,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO AppointmentCode(AppointmentCode,SpecialityGroupId,SourceSystem)
            VALUES(:code,:spec,:sys)
        ")->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

}
