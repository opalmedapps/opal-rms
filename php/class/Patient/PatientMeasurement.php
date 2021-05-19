<?php declare(strict_types = 1);

namespace Orms\Patient;

use Orms\Database;
use Orms\DateTime;
use Orms\Patient\Patient;

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
                PM.PatientMeasurementSer,
                PM.Date,
                PM.Time,
                PM.Weight,
                PM.Height,
                PM.BSA,
                PM.PatientId,
                PM.AppointmentId
            FROM
                PatientMeasurement PM
                INNER JOIN (
                    SELECT
                        MM.PatientMeasurementSer
                    FROM
                        PatientMeasurement MM
                        INNER JOIN Patient P ON P.PatientSerNum = MM.PatientSer
                            AND P.PatientSerNum = :id
                    GROUP BY
                        MM.Date
                    ORDER BY
                        MM.Date DESC,
                        MM.Time DESC
                ) AS PMM ON PMM.PatientMeasurementSer = PM.PatientMeasurementSer
            ORDER BY PM.Date
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
            ":mrn"      => $patient->getActiveMrns()[0]->mrn ."-". $patient->getActiveMrns()[0]->site
        ]);
    }

}
