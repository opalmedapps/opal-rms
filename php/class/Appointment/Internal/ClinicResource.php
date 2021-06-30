<?php declare(strict_types = 1);

namespace Orms\Appointment\Internal;

use Orms\DataAccess\Database;

class ClinicResource
{
    static function getClinicResourceId(string $code,int $specialityGroupId): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ResourceCode = :code
                AND SpecialityGroupId = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
        ]);

        $id = (int) ($query->fetchAll()[0]["ClinicResourcesSerNum"] ?? NULL);
        return $id ?: NULL;
    }

    static function insertClinicResource(string $code,string $description,int $specialityGroupId,string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO ClinicResources(ResourceCode,ResourceName,SpecialityGroupId,SourceSystem)
            VALUES(:code,:desc,:spec,:sys)
        ")->execute([
            ":code"  => $code,
            ":desc"  => $description,
            ":spec"  => $specialityGroupId,
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
