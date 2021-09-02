<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Exception;
use Orms\DataAccess\Database;
use Orms\DateTime;

class AppointmentAccess
{
    public static function getClinicResourceId(string $code, int $specialityGroupId): ?int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                ClinicResourcesSerNum
            FROM
                ClinicResources
            WHERE
                ResourceCode = :code
                AND SpecialityGroupId = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
        ]);

        $id = (int) ($query->fetchAll()[0]["ClinicResourcesSerNum"] ?? null);
        return $id ?: null;
    }

    public static function insertClinicResource(string $code, string $description, int $specialityGroupId, string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO ClinicResources(ResourceCode,ResourceName,SpecialityGroupId,SourceSystem)
            VALUES(:code,:desc,:spec,:sys)
        ")->execute([
            ":code"  => $code,
            ":desc"  => $description,
            ":spec"  => $specialityGroupId,
            ":sys"   => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

    public static function updateClinicResource(int $id, string $description): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE ClinicResources
            SET
                ResourceName = :desc
            WHERE
                ClinicResourcesSerNum = :id
        ")->execute([
            ":desc"  => $description,
            ":id"    => $id
        ]);
    }

    /**
     *
     * @return list<array{
     *      description: string,
     *      code: string
     * }>
     */
    public static function getClinicCodes(int $specialityId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                ResourceName,
                ResourceCode
            FROM
                ClinicResources
            WHERE
                Active = 1
                AND SpecialityGroupId = ?
            ORDER BY
                ResourceName,
                ResourceCode
        ");
        $query->execute([$specialityId]);

        return array_map(fn($x) => [
            "description" => $x["ResourceName"],
            "code"        => $x["ResourceCode"]
        ], $query->fetchAll());
    }

    public static function getAppointmentCodeId(string $code, int $specialityGroupId): ?int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                AppointmentCodeId
            FROM
                AppointmentCode
            WHERE
                AppointmentCode = :code
                AND SpecialityGroupId = :spec
        ");
        $query->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
        ]);

        $id = (int) ($query->fetchAll()[0]["AppointmentCodeId"] ?? null);
        return $id ?: null;
    }

    public static function insertAppointmentCode(string $code, int $specialityGroupId, string $system): int
    {
        $dbh = Database::getOrmsConnection();
        $dbh->prepare("
            INSERT INTO AppointmentCode(AppointmentCode,SpecialityGroupId,SourceSystem)
            VALUES(:code,:spec,:sys)
        ")->execute([
            ":code" => $code,
            ":spec" => $specialityGroupId,
            ":sys"  => $system
        ]);

        return (int) $dbh->lastInsertId();
    }

    /**
     *
     * @return list<array{
     *  AppointmentCode: string
     * }>
     */
    public static function getAppointmentCodes(int $specialityGroupId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                COALESCE(DisplayName,AppointmentCode) AS AppointmentCode
            FROM
                AppointmentCode
            WHERE
                SpecialityGroupId = ?
            ORDER BY
                AppointmentCode
        ");
        $query->execute([$specialityGroupId]);

        return array_map(fn($x) => [
            "AppointmentCode" => $x["AppointmentCode"]
        ],$query->fetchAll());
    }

    /**
     *
     * @return null|array{
     *   sourceId: string,
     *   sourceSystem: string
     * }
     */
    public static function getAppointmentDetails(int $appointmentId): ?array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                AppointId,
                AppointSys
            FROM
                MediVisitAppointmentList
            WHERE
                AppointmentSerNum = ?
        ");
        $query->execute([$appointmentId]);

        $appointment = $query->fetchAll()[0] ?? null;

        if($appointment === null) {
            return null;
        }

        return [
            "sourceId"     => $appointment["AppointId"],
            "sourceSystem" => $appointment["AppointSys"],
        ];
    }

    public static function createOrUpdateAppointment(
        int $appointmentCodeId,
        int $clinicId,
        DateTime $creationDate,
        int $patientId,
        ?string $referringMd,
        DateTime $scheduledDateTime,
        string $sourceId,
        string $status,
        string $system,
        ?string $visitStatus,
    ): void
    {
        Database::getOrmsConnection()->prepare("
            INSERT INTO MediVisitAppointmentList
            SET
                PatientSerNum          = :patSer,
                ClinicResourcesSerNum  = :clinSer,
                ScheduledDateTime      = :schDateTime,
                ScheduledDate          = :schDate,
                ScheduledTime          = :schTime,
                AppointmentCodeId      = :appCodeId,
                AppointId              = :appId,
                AppointSys             = :appSys,
                Status                 = :status,
                MedivisitStatus        = :mvStatus,
                CreationDate           = :creDate,
                ReferringPhysician     = :refPhys,
                LastUpdatedUserIP      = :callIP
            ON DUPLICATE KEY UPDATE
                PatientSerNum           = VALUES(PatientSerNum),
                ClinicResourcesSerNum   = VALUES(ClinicResourcesSerNum),
                ScheduledDateTime       = VALUES(ScheduledDateTime),
                ScheduledDate           = VALUES(ScheduledDate),
                ScheduledTime           = VALUES(ScheduledTime),
                AppointmentCodeId       = VALUES(AppointmentCodeId),
                AppointId               = VALUES(AppointId),
                AppointSys              = VALUES(AppointSys),
                Status                  = CASE WHEN Status = 'Completed' THEN 'Completed' ELSE VALUES(Status) END,
                MedivisitStatus         = VALUES(MedivisitStatus),
                CreationDate            = VALUES(CreationDate),
                ReferringPhysician      = VALUES(ReferringPhysician),
                LastUpdatedUserIP       = VALUES(LastUpdatedUserIP)
        ")->execute([
            ":patSer"       => $patientId,
            ":clinSer"      => $clinicId,
            ":schDateTime"  => $scheduledDateTime->format("Y-m-d H:i:s"),
            ":schDate"      => $scheduledDateTime->format("Y-m-d"),
            ":schTime"      => $scheduledDateTime->format("H:i:s"),
            ":appCodeId"    => $appointmentCodeId,
            ":appId"        => $sourceId,
            ":appSys"       => $system,
            ":status"       => $status,
            ":mvStatus"     => $visitStatus,
            ":creDate"      => $creationDate->format(("Y-m-d H:i:s")),
            ":refPhys"      => $referringMd,
            ":callIP"       => empty($_SERVER["REMOTE_ADDR"]) ? gethostname() : $_SERVER["REMOTE_ADDR"]
        ]);
    }

    public static function completeAppointment(int $appointmentId): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE MediVisitAppointmentList
            SET
                Status = 'Completed'
            WHERE
                AppointmentSerNum = ?
        ")->execute([$appointmentId]);
    }

    public static function updateAppointmentReminderFlag(int $appointmentId): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE MediVisitAppointmentList
            SET
                AppointmentReminderSent = 1
            WHERE
                AppointmentSerNum = ?
        ")->execute([$appointmentId]);
    }

    public static function deleteSimilarAppointments(int $patientId, DateTime $scheduledDateTime, string $clinicCode, string $clinicDescription, int $specialityGroupId): void
    {
        Database::getOrmsConnection()->prepare("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND CR.ResourceCode = :res
                AND CR.SpecialityGroupId = :spec
            INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                AND AC.AppointmentCode = :appCode
                AND AC.SpecialityGroupId = :spec
            SET
                Status = 'Deleted'
            WHERE
                PatientSerNum = :patSer
                AND ScheduledDateTime = :schDateTime
        ")->execute([
            ":patSer"       => $patientId,
            ":schDateTime"  => $scheduledDateTime->format("Y-m-d H:i:s"),
            ":res"          => $clinicCode,
            ":appCode"      => $clinicDescription,
            ":spec"         => $specialityGroupId
        ]);
    }

    public static function completeCheckedInAppointments(): void
    {
        $dbh = Database::getOrmsConnection();

        //add all Patientlocation rows to the MH table
        $dbh->query("
            INSERT INTO PatientLocationMH (
                PatientLocationSerNum,
                PatientLocationRevCount,
                AppointmentSerNum,
                CheckinVenueName,
                ArrivalDateTime,
                DichargeThisLocationDateTime,
                IntendedAppointmentFlag
            )
            SELECT
                PatientLocationSerNum,
                PatientLocationRevCount,
                AppointmentSerNum,
                CheckinVenueName,
                ArrivalDateTime,
                NOW(),
                IntendedAppointmentFlag
            FROM
                PatientLocation
        ");

        //complete the appointment attached to the PatientLocation row
        $dbh->query("
            UPDATE MediVisitAppointmentList MV
            INNER JOIN PatientLocation PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
            SET
                MV.Status = 'Completed'
        ");

        //clear the PatientLocation table
        $dbh->query("DELETE FROM PatientLocation WHERE 1");
    }

    /**
     *
     * @return list<array{
     *  appointmentCode: string,
     *  appointmentId: int,
     *  clinicCode: string,
     *  clinicDescription: string,
     *  patientId: int,
     *  scheduledDatetime: DateTime,
     *  smsActive: bool,
     *  smsReminderSent: bool,
     *  smsType: ?string,
     *  sourceId: string,
     *  sourceSystem: string,
     *  specialityGroupId: int,
     * }>
     */
    public static function getOpenAppointments(DateTime $startDatetime,Datetime $endDatetime, int $patientId = null): array
    {
        $patientFilter = ($patientId === null) ? "" : "AND MV.PatientSerNum = :patId";
        $patientBind   = ($patientId === null) ? [] : [":patId" => $patientId];

        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                MV.AppointmentSerNum,
                MV.PatientSerNum,
                MV.ScheduledDateTime,
                MV.AppointId,
                MV.AppointSys,
                MV.AppointmentReminderSent,
                CR.SpecialityGroupId,
                CR.ResourceCode,
                CR.ResourceName,
                AC.AppointmentCode,
                SA.Active,
                SA.Type
            FROM
                MediVisitAppointmentList MV
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                INNER JOIN SmsAppointment SA ON SA.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                    AND SA.AppointmentCodeId = MV.AppointmentCodeId
            WHERE
                MV.ScheduledDateTime BETWEEN :startDate AND :endDate
                AND MV.Status = 'Open'
                $patientFilter
            ORDER BY
                MV.ScheduledDateTime,
                MV.AppointmentSerNum
        ");
        $query->execute(array_merge(
            [
                ":startDate"   => $startDatetime->format("Y-m-d H:i:s"),
                ":endDate"     => $endDatetime->format("Y-m-d H:i:s")
            ],
            $patientBind
        ));

        return array_map(fn($x) => [
            "appointmentCode"       => $x["AppointmentCode"],
            "appointmentId"         => (int) $x["AppointmentSerNum"],
            "clinicCode"            => $x["ResourceCode"],
            "clinicDescription"     => $x["ResourceName"],
            "patientId"             => (int) $x["PatientSerNum"],
            "scheduledDatetime"     => DateTime::createFromFormatN("Y-m-d H:i:s", $x["ScheduledDateTime"]) ?? throw new Exception("Invalid appointment datetime"),
            "smsActive"             => (bool) $x["Active"],
            "smsReminderSent"       => (bool) $x["AppointmentReminderSent"],
            "smsType"               => $x["Type"] ?? null,
            "sourceId"              => $x["AppointId"],
            "sourceSystem"          => $x["AppointSys"],
            "specialityGroupId"     => (int) $x["SpecialityGroupId"],
        ],$query->fetchAll());
    }

    /**
     * Returns the revCount of the PatentLocation row that was removed, or 0 if there was none
     */
    public static function removeAppointmentLocation(int $appointmentId): int
    {
        $dbh = Database::getOrmsConnection();

        //check if the patient is already checked in for this appointment
        $queryExistingCheckIn = $dbh->prepare("
            SELECT DISTINCT
                PL.PatientLocationSerNum,
                PL.PatientLocationRevCount,
                PL.CheckinVenueName,
                PL.ArrivalDateTime,
                PL.IntendedAppointmentFlag
            FROM
                PatientLocation PL
            WHERE
                PL.AppointmentSerNum = ?
        ");
        $queryExistingCheckIn->execute([$appointmentId]);

        $currentLocation = $queryExistingCheckIn->fetchAll()[0] ?? null;

        if($currentLocation === null) {
            return 0;
        }

        //move the old location to the MH table and then delete it
        $dbh->prepare("
            INSERT INTO PatientLocationMH
            SET
                PatientLocationSerNum    = :plId,
                PatientLocationRevCount  = :revCount,
                AppointmentSerNum        = :appId,
                CheckinVenueName         = :room,
                ArrivalDateTime          = :arrival,
                IntendedAppointmentFlag  = :intended
        ")->execute([
            "plId"       => $currentLocation["PatientLocationSerNum"],
            ":revCount"  => $currentLocation["PatientLocationRevCount"],
            ":appId"     => $appointmentId,
            ":room"      => $currentLocation["CheckinVenueName"],
            ":arrival"   => $currentLocation["ArrivalDateTime"],
            ":intended"  => $currentLocation["IntendedAppointmentFlag"]
        ]);

        $dbh->prepare("
            DELETE FROM PatientLocation
            WHERE
                PatientLocationSerNum = ?
        ")->execute([$currentLocation["PatientLocationSerNum"]]);

        return (int) $currentLocation["PatientLocationRevCount"];
    }

    public static function moveAppointmentToLocation(int $appointmentId, string $room, bool $intendedAppointment): void
    {
        //remove any current PatientLocation rows for this appointment
        $currentRevCount = self::removeAppointmentLocation($appointmentId) +1;

        //insert the new location
        Database::getOrmsConnection()->prepare("
            INSERT INTO PatientLocation
            SET
                PatientLocationRevCount = :revCount,
                AppointmentSerNum       = :appId,
                CheckinVenueName        = :room,
                ArrivalDateTime         = NOW(),
                IntendedAppointmentFlag = :intended
        ")->execute([
            ":revCount"  => $currentRevCount,
            ":appId"     => $appointmentId,
            ":room"      => $room,
            ":intended"  => (int) $intendedAppointment
        ]);
    }
}
