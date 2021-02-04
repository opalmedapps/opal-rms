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
        self::$dbh = Database::getOrmsConnection();
    }

    static function regenerateClinicResources(): void
    {
        self::$dbh->beginTransaction();

        self::_backupAndUpdateResourceTable();
        self::_insertClinicResources();
        self::_relinkAppointments();

        self::$dbh->commit();
    }

    private static function _backupAndUpdateResourceTable(): void
    {
        self::$dbh->query("
            CREATE TEMPORARY TABLE COPY_ClinicResources
                SELECT * FROM ClinicResources;
        ");

        self::$dbh->query("
            DELETE FROM ClinicResources;
        ");

        self::$dbh->query("
            ALTER TABLE `ClinicResources`
            CHANGE COLUMN `ClinicResourcesSerNum` `ClinicResourcesSerNum` INT(11) NOT NULL AUTO_INCREMENT FIRST,
            ADD COLUMN `ResourceCode` VARCHAR(200) NOT NULL AFTER `ClinicResourcesSerNum`,
            ADD COLUMN `SourceSystem` VARCHAR(50) NOT NULL AFTER `Speciality`;
        ");
    }

    private static function _insertClinicResources(): void
    {
        #some add-ons have a trailing newline (which they shouldn't have...)
        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET
                MV.Resource = TRIM(TRAILING '\n' FROM MV.Resource)
        ");

        $queryClinicCodes = self::$dbh->query("
            SELECT DISTINCT
                MV.Resource
                ,MV.ResourceDescription
                ,MV.AppointSys
                ,CR.Speciality
            FROM
                MediVisitAppointmentList MV
            INNER JOIN
                COPY_ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            WHERE
                MV.AppointSys != 'InstantAddOn'
            ORDER BY
                MV.AppointSys
                ,MV.Resource;
        ");
        $codes = $queryClinicCodes->fetchAll() ?: [];

        $insertClinicCodes = self::$dbh->prepare("
            INSERT INTO ClinicResources(ResourceCode,ResourceName,Speciality,SourceSystem)
            VALUES(:code,:desc,:spec,:sys);
        ");
        foreach($codes as $code) {
            $insertClinicCodes->execute([
                ":code" => $code["Resource"],
                ":desc" => $code["ResourceDescription"],
                ":spec" => $code["Speciality"],
                ":sys"  => $code["AppointSys"]
            ]);
        }
    }

    private static function _relinkAppointments(): void
    {
        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET MV.ClinicResourcesSerNum = 0
            WHERE 1;
        ");

        #some appointments (addons) have a non-existant resource (ie not in Medivisit) so remove them
        self::$dbh->query("
            DELETE MediVisitAppointmentList
            FROM MediVisitAppointmentList
            LEFT JOIN ClinicResources CR ON CR.ResourceCode = MediVisitAppointmentList.Resource
                AND CR.ResourceName = MediVisitAppointmentList.ResourceDescription
            WHERE
                CR.ResourceCode IS NULL
                AND MediVisitAppointmentList.AppointSys = 'InstantAddOn'
        ");

        self::$dbh->query("
            DELETE FROM PatientLocation WHERE PatientLocation.AppointmentSerNum NOT IN (SELECT MV.AppointmentSerNum FROM MediVisitAppointmentList MV);
        ");

        self::$dbh->query("
            DELETE FROM PatientLocationMH WHERE PatientLocationMH.AppointmentSerNum NOT IN (SELECT MV.AppointmentSerNum FROM MediVisitAppointmentList MV);
        ");

        self::$dbh->query("
            UPDATE MediVisitAppointmentList MV
            SET
                MV.ClinicResourcesSerNum = (
                    SELECT
                        CR.ClinicResourcesSerNum
                    FROM
                        ClinicResources CR
                    WHERE
                        CR.ResourceCode = MV.Resource
                        AND CR.ResourceName = MV.ResourceDescription
                )
            WHERE
                MV.ClinicResourcesSerNum = 0
        ");
    }
}


?>
