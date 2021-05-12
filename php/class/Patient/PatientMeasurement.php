<?php declare(strict_types = 1);

namespace Orms\Patient;

use Orms\Database;
use Orms\DateTime;
use Orms\Patient;

/** @psalm-immutable */
class PatientMeasurement
{
    private function __construct(
        public string $id,
        public string $appointmentId,
        public string $mrnSite,
        public DateTime $datetime,
        public float $weight,
        public float $height,
        public float $bsa
    ) {}

    /**
     *
     * @return PatientMeasurement[]
     */
    static function getMeasurements(Patient $patient): array
    {
        $dbh = Database::getOrmsConnection();

        #gets the lastest measurement taken during each date to take into account the fact that a patient in be reweighed (in case of an error, etc)
        $query = $dbh->prepare("
            SELECT
                PatientMeasurement.PatientMeasurementSer,
                PatientMeasurement.Date,
                PatientMeasurement.Time,
                PatientMeasurement.Weight,
                PatientMeasurement.Height,
                PatientMeasurement.BSA,
                PatientMeasurement.PatientId,
                PatientMeasurement.AppointmentId
            FROM
                PatientMeasurement
                INNER JOIN (
                    SELECT
                        P.FirstName,
                        P.LastName,
                        PatientMeasurement.PatientMeasurementSer
                    FROM
                        PatientMeasurement
                        INNER JOIN Patient ON P.PatientSerNum = PatientMeasurement.PatientSer
                            AND P.PatientSerNum = :id
                    GROUP BY
                        PatientMeasurement.Date
                    ORDER BY
                        PatientMeasurement.Date DESC,
                        PatientMeasurement.Time DESC
                ) AS PM ON PM.PatientMeasurementSer = PatientMeasurement.PatientMeasurementSer
            ORDER BY PatientMeasurement.Date
        ");
        $query->execute([
            ":id" => $patient->id
        ]);

        return array_map(function($x) {
            return new PatientMeasurement(
               id:              $x["PatientMeasurementSer"],
               appointmentId:   $x["AppointmentId"],
               mrnSite:         $x["PatientId"],
               datetime:        new DateTime($x["Date"] ." ". $x["Time"]),
               weight:          (float) $x["Weight"],
               height:          (float) $x["Height"],
               bsa:             (float) $x["BSA"],
            );
        },$query->fetchAll());
    }

    static function insertMeasurement(Patient $patient,float $height,float $weight,float $bsa,string $appointmentSourceId,string $appointmentSourceSystem): void
    {
        Database::getOrmsConnection()->prepare("
            INSERT INTO PatientMeasurement
            SET
                PatientSer      = :pSer,
                Date            = CURDATE(),
                Time            = CURTIME(),
                Height          = :height,
                Weight          = :weight,
                BSA             = :bsa,
                AppointmentId   = :appId,
                PatientId       = :mrn
        ")->execute([
            ":pSer"     => $patient->id,
            ":height"   => $height,
            ":weight"   => $weight,
            ":bsa"      => $bsa,
            ":appId"    => "$appointmentSourceSystem-$appointmentSourceId",
            ":mrn"      => $patient->mrns[0]->mrn ."-". $patient->mrns[0]->site
        ]);
    }

}
