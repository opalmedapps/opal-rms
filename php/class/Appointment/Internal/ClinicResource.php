<?php declare(strict_types = 1);

namespace Orms\Appointment\Internal;

use Orms\Database;

class ClinicResource
{
    static function getClinicResourceId(string $code,string $specialityGroup): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ResourceCode = :code
                AND Speciality = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroup,
        ]);

        $id = (int) ($query->fetchAll()[0]["ClinicResourcesSerNum"] ?? NULL);
        return $id ?: NULL;
    }

    static function insertClinicResource(string $code,string $description,string $specialityGroup,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO ClinicResources(ResourceCode,ResourceName,Speciality,SourceSystem)
            VALUES(:code,:desc,:spec,:sys)
        ")->execute([
            ":code"  => $code,
            ":desc"  => $description,
            ":spec"  => $specialityGroup,
            ":sys"   => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

    static function updateClinicResource(int $id,string $description): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE ClinicResources
            SET
                ResourceName = :desc
            WHERE
                ClinicResourcesSerNum = :id
        ")->execute([
            ":desc"  => $description,
            ":id"    => $id
        ]);
    }

}
