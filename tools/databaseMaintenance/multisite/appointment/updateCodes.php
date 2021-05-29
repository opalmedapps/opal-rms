<?php declare(strict_types = 1);

require_once __DIR__ ."/../../../../vendor/autoload.php";

use Orms\Util\Csv;

class AppointmentCodes
{
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
}
