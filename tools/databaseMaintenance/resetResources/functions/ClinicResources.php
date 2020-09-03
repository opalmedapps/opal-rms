<?php declare(strict_types = 1);

#loads all clinic resources from the ORMS appointment list and re-inserts them into the database
require_once __DIR__ ."/../../../../vendor/autoload.php";

class ClinicResources
{
    static function regenerateClinicResources()
    {
        self::_backupAndUpdateResourceTable();
        self::_insertClinicResources();
        self::_relinkAppointments();
        self::_cleanup();
    }

    private static function _backupAndUpdateResourceTable()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
            CREATE TABLE COPY_ClinicResources
            SELECT * FROM ClinicResources
        ");

        $dbh->query("
            TRUNCATE TABLE ClinicResources;
        ");

        $dbh->query("
            ALTER TABLE `ClinicResources`
            CHANGE COLUMN `ClinicResourcesSerNum` `ClinicResourcesSerNum` INT(11) NOT NULL AUTO_INCREMENT FIRST
            ADD COLUMN `ResourceCode` VARCHAR(200) NOT NULL AFTER `ClinicResourcesSerNum`;
        ");

        $dbh->commit();
    }

    private static function _insertClinicResources()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $queryClinicCodes = $dbh->prepare("
            SELECT DISTINCT
                MV.Resource
                ,MV.ResourceDescription
                ,CR.Speciality
            FROM MediVisitAppointmentList MV
            INNER JOIN COPY_ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum;
        ");
        $codes = $queryClinicCodes->fetchAll();

        $insertClinicCodes = $dbh->prepare("
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

        $dbh->commit();
    }

    private static function _relinkAppointments()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.ClinicResourcesSerNum = NULL
            WHERE 1;
        ");

        $updateSerNum = $dbh->query("
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

        $dbh->commit();
    }

    private static function _cleanup()
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $dbh->beginTransaction();

        $dbh->query("
            DROP TABLE COPY_ClinicResources;
        ");

        $dbh->commit();
    }
}


?>
