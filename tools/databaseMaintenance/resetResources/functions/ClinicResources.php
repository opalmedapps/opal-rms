<?php declare(strict_types = 1);

#loads all clinic resources from the ORMS appointment list and re-inserts them into the database
require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Config;

ClinicResources::__init();

class ClinicResources
{
    private static PDO $dbh;

    public static function __init(): void
    {
        self::$dbh = Config::getDatabaseConnection("ORMS");
    }

    static function regenerateClinicResources(): void
    {
        self::$dbh->beginTransaction();

        self::_backupAndUpdateResourceTable();
        self::_insertClinicResources();
        self::_relinkAppointments();
        self::_cleanup();

        self::$dbh->commit();
    }

    private static function _backupAndUpdateResourceTable(): void
    {
        self::$dbh->query("
            CREATE TABLE COPY_ClinicResources
            SELECT * FROM ClinicResources
        ");

        self::$dbh->query("
            TRUNCATE TABLE ClinicResources;
        ");

        self::$dbh->query("
            ALTER TABLE `ClinicResources`
            CHANGE COLUMN `ClinicResourcesSerNum` `ClinicResourcesSerNum` INT(11) NOT NULL AUTO_INCREMENT FIRST
            ADD COLUMN `ResourceCode` VARCHAR(200) NOT NULL AFTER `ClinicResourcesSerNum`;
        ");
    }

    private static function _insertClinicResources(): void
    {
        $queryClinicCodes = self::$dbh->prepare("
            SELECT DISTINCT
                MV.Resource
                ,MV.ResourceDescription
                ,CR.Speciality
            FROM MediVisitAppointmentList MV
            INNER JOIN COPY_ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum;
        ");
        $codes = $queryClinicCodes->fetchAll() ?: [];

        $insertClinicCodes = self::$dbh->prepare("
            INSERT INTO ClinicResources(ResourceCode,ResourceName,Speciality)
            VALUES(:code,:desc,:spec);
        ");
        foreach($codes as $code) {
            $insertClinicCodes->execute([
                ":code" => $code["Resource"],
                ":desc" => $code["ResourceDescription"],
                ":spec" => $code["Speciality"],
            ]);
        }
    }

    private static function _relinkAppointments(): void
    {
        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.ClinicResourcesSerNum = NULL
            WHERE 1;
        ");

        $updateSerNum = self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET
                MV.ClinicResourcesSerNum = (
                    SELECT
                        ClinicResources.ClinicResourcesSerNum
                    FROM
                        ClinicResources
                    WHERE
                        ClinicResources.ResourceName = :name
                        AND ClinicResources.ResourceDescription = :desc
                )
            WHERE
                MV.ClinicResourcesSerNum IS NULL;
        ");
    }

    private static function _cleanup(): void
    {
        self::$dbh->query("
            DROP TABLE COPY_ClinicResources;
        ");
    }
}


?>
