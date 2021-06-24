<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Util\Csv;

class AppointmentCodes
{
    static function extendCodeLength(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `AppointmentCode`
            CHANGE COLUMN `AppointmentCode` `AppointmentCode` VARCHAR(100) NOT NULL COLLATE 'latin1_swedish_ci' AFTER `AppointmentCodeId`;
        ");
    }

    static function addDisplayColumn(PDO $dbh): void
    {
        $dbh->query("
            ALTER TABLE `AppointmentCode`
            ADD COLUMN `DisplayName` VARCHAR(100) NULL DEFAULT NULL AFTER `AppointmentCode`;
        ");
    }

    static function correctSourceSystem(PDO $dbh): void
    {
        $dbh->query("
            UPDATE AppointmentCode
            SET
                SourceSystem = 'MEDIVISIT'
            WHERE
                SourceSystem = 'Medivisit'
        ");

        $dbh->query("
            UPDATE ClinicResources
            SET
                SourceSystem = 'MEDIVISIT'
            WHERE
                SourceSystem = 'Medivisit'
        ");

        $dbh->query("
            UPDATE MediVisitAppointmentList
            SET
                AppointSys = 'MEDIVISIT'
            WHERE
                AppointSys = 'Medivisit'
        ");

        $dbh->query("
            UPDATE SmsAppointment
            SET
                SourceSystem = 'MEDIVISIT'
            WHERE
                SourceSystem = 'Medivisit'
        ");
    }

    //merges Aria zoom and teams appointments
    //the zoom appointments are merged into the teams ones
    static function fixZoomAppointments(PDO $dbh): void
    {
        //get the teams codes that we need to merge
        $queryTeams = $dbh->prepare("
            SELECT
                AppointmentCodeId,
                AppointmentCode
            FROM
                AppointmentCode
            WHERE
                AppointmentCode IN (
                    'FOLLOW UP TEAMS MORE/30 DAYS',
                    'FOLLOW UP TEAMS LESS/30 DAYS',
                    'CONSULT RETURN TEAMS',
                    'CONSULT NEW TEAMS'
                )
        ");
        $queryTeams->execute();

        $queryZoom = $dbh->prepare("
            SELECT
                AppointmentCodeId,
                AppointmentCode
            FROM
                AppointmentCode
            WHERE
                AppointmentCode = ?
        ");

        $codes = [];
        foreach($queryTeams->fetchAll() as $x)
        {
            //get the associated zoom code
            $queryZoom->execute([str_replace("TEAMS","ZOOM",$x["AppointmentCode"])]);
            $zoomCode = $queryZoom->fetchAll()[0] ?? NULL;

            if($zoomCode !== NULL) {
                $codes[] = [
                    "teamsId"   => $x["AppointmentCodeId"],
                    "teamsCode" => $x["AppointmentCode"],
                    "zoomId"    => $zoomCode["AppointmentCodeId"],
                    "zoomCode"  => $zoomCode["AppointmentCode"]
                ];
            }
        }

        //perform the merges
        $updateMergeAppointments = $dbh->prepare("
            UPDATE MediVisitAppointmentList
            SET AppointmentCodeId = :newCode
            WHERE AppointmentCodeId = :oldCode
        ");

        $updateMergeSms = $dbh->prepare("
            UPDATE IGNORE SmsAppointment
            SET
                AppointmentCodeId = :newCode,
                Type = NULL,
                Active = 0
            WHERE AppointmentCodeId = :oldCode
        ");

        $deleteSms = $dbh->prepare("
            DELETE FROM SmsAppointment
            WHERE AppointmentCodeId = :oldCode;
        ");

        $deleteCode = $dbh->prepare("
            DELETE FROM AppointmentCode
            WHERE AppointmentCodeId = :oldCode
        ");

        foreach($codes as $x)
        {
            $updateMergeAppointments->execute([
                ":newCode" => $x["teamsId"],
                ":oldCode" => $x["zoomId"]
            ]);

            $updateMergeSms->execute([
                ":newCode" => $x["teamsId"],
                ":oldCode" => $x["zoomId"]
            ]);

            $deleteSms->execute([
                ":oldCode" => $x["zoomId"]
            ]);

            $deleteCode->execute([
                ":oldCode" => $x["zoomId"]
            ]);
        }
    }

    //opens a file containing Aria appointment codes and descriptions and updates ORMS to use the appointment code instead of the description
    static function fixAriaAppointmentCodes(PDO $dbh,string $csvFilename): void
    {
        $codes = Csv::loadCsvFromFile($csvFilename);

        $updateAppointmentCodes = $dbh->prepare("
            UPDATE AppointmentCode
            SET
                AppointmentCode = :code,
                DisplayName = :desc
            WHERE
                AppointmentCode = :desc
        ");

        $updateProfileOptions = $dbh->prepare("
            UPDATE ProfileOptions
            SET
                Options = :code
            WHERE
                Options = :desc
        ");

        foreach($codes as $code)
        {
            $updateAppointmentCodes->execute([
                ":code" => $code["Activity Code"],
                ":desc" => $code["Activity Description"]
            ]);

            $updateProfileOptions->execute([
                ":code" => $code["Activity Code"],
                ":desc" => $code["Activity Description"]
            ]);
        }
    }

    //remove all add-ons appointments from ORMS, and all connected information
    static function deleteAddOns(PDO $dbh): void
    {
        $dbh->query("
            DELETE FROM AppointmentCode
            WHERE
                AppointmentCode = 'ADD-ON'
        ");

        $dbh->query("
            DELETE FROM MediVisitAppointmentList
            WHERE
                AppointSys = 'InstantAddOn'
                AND AppointmentCode = 'ADD-ON'
        ");

        $dbh->query("
            DELETE FROM MediVisitAppointmentList
            WHERE
                AppointSys = 'InstantAddOn'
                AND AppointmentCodeId = 0
        ");

        $dbh->query("
            DELETE FROM PatientLocationMH
            WHERE
                AppointmentSerNum NOT IN (SELECT AppointmentSerNum FROM MediVisitAppointmentList)
        ");

        $dbh->query("
            DELETE FROM PatientMeasurement
            WHERE
                REPLACE(AppointmentId,'Medivisit-','') NOT IN (SELECT AppointId FROM MediVisitAppointmentList)
                AND AppointmentId NOT LIKE '%Aria%';
        ");
    }
}
