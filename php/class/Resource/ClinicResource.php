<?php declare(strict_types = 1);

namespace Orms\Resource;

use Orms\Database;

class ClinicResource
{
    static function getClinicResourceId(string $code,string $description,string $specialityGroup): ?int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ResourceCode = :code
                AND ResourceName = :desc
                AND Speciality = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":desc" => $description,
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

}

?>
