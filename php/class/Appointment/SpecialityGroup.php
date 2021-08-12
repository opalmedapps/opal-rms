<?php

declare(strict_types=1);

namespace Orms\Appointment;

use Exception;
use Orms\DataAccess\Database;

class SpecialityGroup
{
    public static function getSpecialityGroupId(string $code): int
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                SpecialityGroupId
            FROM
                SpecialityGroup
            WHERE
                SpecialityGroupCode = ?
        ");
        $query->execute([$code]);

        $id = $query->fetchAll()[0]["SpecialityGroupId"] ?? null;

        if($id === null) {
            throw new Exception("Unknown speciality group code $code");
        }

        return (int) $id;
    }
}
