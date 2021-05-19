<?php declare(strict_types = 1);

namespace Orms\Resource;

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

    static function insertAppointmentCode(string $code,string $description,string $specialityGroup,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO AppointmentCode(AppointmentCode,AppointmentCodeDescription,Speciality,SourceSystem)
            VALUES(:code,:desc,:spec,:sys)
        ")->execute([
            ":code" => $code,
            ":desc" => $description,
            ":spec" => $specialityGroup,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

}

?>
