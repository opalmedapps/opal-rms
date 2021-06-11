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

    //opens a file containing Aria appointment codes and descriptions and updates ORMS to use the appointment code instead of the description
    static function fixAriaAppointmentCodes(PDO $dbh,string $csvFilename): void
    {
        $codes = Csv::loadCsvFromFile($csvFilename);

        $updateAppointmentCodes = $dbh->prepare("
            UPDATE AppointmentCode
            SET
                AppointmentCode = :code
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
