<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Orms\DataAccess\Database;
use Orms\DateTime;

class ReportAccess
{
    /**
     * @param string[] $statusFilter
     * @param string[] $codeFilter
     * @return list<array{
     *      fname: string,
     *      lname: string,
     *      mrn: string,
     *      site: string,
     *      ramq: ?string,
     *      appName: string,
     *      appClinic: string,
     *      appType: string,
     *      appStatus: string,
     *      appDay: string,
     *      appTime: string,
     *      checkin: ?string,
     *      createdToday: bool,
     *      referringPhysician: ?string,
     *      mediStatus: ?string
     * }>
     */
    public static function getListOfAppointmentsInDateRange(DateTime $startDate, DateTime $endDate, int $specialiyGroupId, array $statusFilter, array $codeFilter): array
    {
        $sql = "
            SELECT
                MV.AppointmentSerNum,
                P.FirstName,
                P.LastName,
                PH.MedicalRecordNumber,
                H.HospitalCode,
                PI.InsuranceNumber,
                CR.ResourceName,
                CR.ResourceCode,
                AC.AppointmentCode,
                MV.Status,
                MV.ScheduledDate,
                MV.ScheduledTime,
                MV.CreationDate,
                MV.ReferringPhysician,
                (select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePL,
                (select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as ArrivalDateTimePLM,
                MV.MedivisitStatus
            FROM
                Patient P
                INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
                    AND MV.Status != 'Deleted'
                    AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
                    AND :statusFilter:
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                    AND :codeFilter:
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
                    AND SG.SpecialityGroupId = :spec
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                    AND PH.HospitalId = SG.HospitalId
                    AND PH.Active = 1
                INNER JOIN Hospital H ON H.HospitalId = SG.HospitalId
                LEFT JOIN Insurance I ON I.InsuranceCode = 'RAMQ'
                LEFT JOIN PatientInsuranceIdentifier PI ON PI.InsuranceId = I.InsuranceId
                    AND PI.PatientId = P.PatientSerNum
            WHERE
                PH.MedicalRecordNumber NOT LIKE '999999%'
            ORDER BY ScheduledDate,ScheduledTime
        ";

        $sqlStringWithStatus = Database::generateBoundedSqlString($sql, ":statusFilter:", "MV.Status", $statusFilter);
        $sqlStringWithCode = Database::generateBoundedSqlString($sqlStringWithStatus["sqlString"], ":codeFilter:", "CR.ResourceName", $codeFilter);

        $query = Database::getOrmsConnection()->prepare($sqlStringWithCode["sqlString"]);
        $query->execute(array_merge(
            [
                ":sDate" => $startDate->format("Y-m-d H:i:s"),
                ":eDate" => $endDate->format("Y-m-d H:i:s"),
                ":spec"  => $specialiyGroupId
            ],
            $sqlStringWithStatus["boundValues"],
            $sqlStringWithCode["boundValues"]
        ));

        return array_map(function($x) {
            return [
                "fname"                 => $x["FirstName"],
                "lname"                 => $x["LastName"],
                "mrn"                   => $x["MedicalRecordNumber"],
                "site"                  => $x["HospitalCode"],
                "ramq"                  => $x["InsuranceNumber"] ?? null,
                "appName"               => $x["ResourceName"],
                "appClinic"             => $x["ResourceCode"],
                "appType"               => $x["AppointmentCode"],
                "appStatus"             => $x["Status"],
                "appDay"                => $x["ScheduledDate"],
                "appTime"               => mb_substr($x["ScheduledTime"], 0, -3),
                "checkin"               => $x["ArrivalDateTimePL"] ?? $x["ArrivalDateTimePLM"],
                "createdToday"          => (new DateTime($x["CreationDate"]))->format("Y-m-d") === (new DateTime($x["ScheduledDate"]))->format("Y-m-d"), //"today" refers to the date of the appointment
                "referringPhysician"    => $x["ReferringPhysician"],
                "mediStatus"            => $x["MedivisitStatus"],
            ];
        }, $query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *  LastName: string,
     *  FirstName: string,
     *  Mrn: string,
     *  Site: string,
     *  ResourceCode: string,
     *  ResourceName: string,
     *  AppointmentCode: string,
     *  ScheduledDateTime: string,
     *  Status: string,
     *  CheckinVenueName: string,
     *  ArrivalDateTime: string,
     *  DichargeThisLocationDateTime: string,
     *  Duration: string
     * }>
     */
    public static function getChemoAppointments(DateTime $startDate, DateTime $endDate): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                P.LastName,
                P.FirstName,
                PH.MedicalRecordNumber AS Mrn,
                H.HospitalCode AS Site,
                CR.ResourceCode,
                CR.ResourceName,
                AC.AppointmentCode,
                MV.ScheduledDateTime,
                MV.Status,
                PL.CheckinVenueName,
                PL.ArrivalDateTime,
                PL.DichargeThisLocationDateTime,
                TIMEDIFF(PL.DichargeThisLocationDateTime,PL.ArrivalDateTime) AS Duration
            FROM
                Patient P
                INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
                    AND MV.Status = 'Completed'
                    AND MV.ScheduledDateTime BETWEEN :sDate AND :eDate
                INNER JOIN PatientLocationMH PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
                    AND PL.PatientLocationRevCount = (
                        SELECT MIN(PatientLocationMH.PatientLocationRevCount)
                        FROM PatientLocationMH
                        WHERE
                            PatientLocationMH.AppointmentSerNum = MV.AppointmentSerNum
                            AND PatientLocationMH.CheckinVenueName LIKE '%TX AREA%'
                    )
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                        AND AC.AppointmentCode LIKE '%CHM%'
                INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                    AND PH.HospitalId = SG.HospitalId
                    AND PH.Active = 1
                INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
            ORDER BY
                MV.ScheduledDateTime,
                Site,
                Mrn
        ");
        $query->execute([
            ":sDate" => $startDate->format("Y-m-d H:i:s"),
            ":eDate" => $endDate->format("Y-m-d H:i:s")
        ]);

        return array_map(function($x) {
            return [
                "LastName"                      => $x["LastName"],
                "FirstName"                     => $x["FirstName"],
                "Mrn"                           => $x["Mrn"],
                "Site"                          => $x["Site"],
                "ResourceCode"                  => $x["ResourceCode"],
                "ResourceName"                  => $x["ResourceName"],
                "AppointmentCode"               => $x["AppointmentCode"],
                "ScheduledDateTime"             => $x["ScheduledDateTime"],
                "Status"                        => $x["Status"],
                "CheckinVenueName"              => $x["CheckinVenueName"],
                "ArrivalDateTime"               => $x["ArrivalDateTime"],
                "DichargeThisLocationDateTime"  => $x["DichargeThisLocationDateTime"],
                "Duration"                      => $x["Duration"],
            ];
        }, $query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *  ResourceName: string,
     *  CheckinVenueName: string,
     *  ScheduledDate: string
     * }>
     */
    public static function getRoomUsage(DateTime $startDate, DateTime $endDate, DateTime $startTime, DateTime $endTime, int $specialityGroupId): array
    {
        //get a list of all rooms that patients were checked into and for which appointment
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                CR.ResourceName,
                PatientLocationMH.CheckinVenueName,
                MV.ScheduledDate
            FROM
                MediVisitAppointmentList MV
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                    AND CR.SpecialityGroupId = :spec
                INNER JOIN PatientLocationMH PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MV.AppointmentSerNum
                    AND PatientLocationMH.CheckinVenueName NOT IN ('VISIT COMPLETE','ADDED ON BY RECEPTION','BACK FROM X-RAY/PHYSIO','SENT FOR X-RAY','SENT FOR PHYSIO','RC RECEPTION','OPAL PHONE APP')
                    AND PatientLocationMH.CheckinVenueName NOT LIKE '%WAITING ROOM%'
                    AND CAST(PatientLocationMH.ArrivalDateTime AS TIME) BETWEEN :sTime AND :eTime
            WHERE
                MV.ScheduledDateTime BETWEEN :sDate AND :eDate
                AND MV.Status = 'Completed'
        ");
        $query->execute([
            ":sDate" => $startDate->format("Y-m-d H:i:s"),
            ":eDate" => $endDate->format("Y-m-d H:i:s"),
            ":sTime" => $startTime->format("H:i:s"),
            ":eTime" => $endTime->format("H:i:s"),
            ":spec"  => $specialityGroupId
        ]);

        return array_map(function($x) {
            return [
                "ResourceName"     => $x["ResourceName"],
                "CheckinVenueName" => $x["CheckinVenueName"],
                "ScheduledDate"    => $x["ScheduledDate"],
            ];
        }, $query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *  fname: string,
     *  lname: string,
     *  mrn: string,
     *  site: string,
     *  room: string,
     *  day: string,
     *  arrival: string,
     *  discharge: string,
     *  startTime: string,
     *  resourceCode: string,
     *  appointmentId: int
     * }>
     */
    public static function getWaitingRoomAppointments(DateTime $startDate, DateTime $endDate, int $specialityGroupId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT DISTINCT
                CR.ResourceCode,
                CR.ResourceName,
                MV.ScheduledDateTime,
                PL.AppointmentSerNum,
                P.FirstName,
                P.LastName,
                PH.MedicalRecordNumber,
                H.HospitalCode,
                PL.PatientLocationRevCount,
                CAST(PL.ArrivalDateTime AS DATE) AS Date,
                PL.ArrivalDateTime AS Arrival,
                PL.DichargeThisLocationDateTime AS Discharge,
                PL.CheckinVenueName
            FROM
                MediVisitAppointmentList MV
                INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
                INNER JOIN PatientLocationMH PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
                    AND (
                        PL.CheckinVenueName LIKE '%Waiting%'
                        OR PL.CheckinVenueName LIKE '%WAITING%'
                    )
                    AND PL.ArrivalDateTime BETWEEN :sDate AND :eDate
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
                    AND SG.SpecialityGroupId = :spec
                INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                    AND PH.HospitalId = SG.HospitalId
                    AND PH.Active = 1
                INNER JOIN Hospital H ON H.HospitalId = SG.HospitalId
            ORDER BY
                P.LastName,
                P.FirstName,
                PL.AppointmentSerNum,
                PL.ArrivalDateTime
        ");
        $query->execute([
            ":sDate" => $startDate->format("Y-m-d H:i:s"),
            ":eDate" => $endDate->format("Y-m-d H:i:s"),
            ":spec"  => $specialityGroupId
        ]);

        return array_map(function($x) {
            return [
                "fname"         => $x["FirstName"],
                "lname"         => $x["LastName"],
                "mrn"           => $x["MedicalRecordNumber"],
                "site"          => $x["HospitalCode"],
                "room"          => $x["CheckinVenueName"],
                "day"           => $x["Date"],
                "arrival"       => $x["Arrival"],
                "discharge"     => $x["Discharge"],
                "startTime"     => $x["ScheduledDateTime"],
                "resourceCode"  => $x["ResourceCode"],
                "appointmentId" => (int) $x["AppointmentSerNum"]
            ];
        }, $query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *   Age: int,
     *   AppointmentId: int,
     *   AppointmentName: string,
     *   ArrivalDateTime: ?string,
     *   ArrivalDateTime_hh: ?int,
     *   ArrivalDateTime_mm: ?int,
     *   Birthday: string,
     *   BSA: ?float,
     *   CheckinSystem: string,
     *   FirstName: string,
     *   Height: ?float,
     *   LanguagePreference: string,
     *   LastName: string,
     *   LastQuestionnaireReview: ?string,
     *   Mrn: string,
     *   OpalPatient: int,
     *   PatientId: int,
     *   ResourceName: string,
     *   ScheduledStartTime: string,
     *   ScheduledStartTime_hh: int,
     *   ScheduledStartTime_mm: int,
     *   Sex: string,
     *   Site: string,
     *   SMSAlertNum: string,
     *   SourceId: string,
     *   SpecialityGroupId: int,
     *   Status: string,
     *   TimeRemaining: int,
     *   VenueId: ?string,
     *   WaitTime: int,
     *   Weight: ?float,
     *   WeightDate: ?string
     * }>
     */
    public static function getCurrentDaysAppointments(): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                MV.AppointmentSerNum AS AppointmentId,
                MV.AppointId AS SourceId,
                SG.SpecialityGroupId,
                PL.ArrivalDateTime,
                COALESCE(AC.DisplayName,AC.AppointmentCode) AS AppointmentName,
                P.LastName,
                P.FirstName,
                P.PatientSerNum AS PatientId,
                PH.MedicalRecordNumber AS Mrn,
                H.HospitalCode AS Site,
                P.OpalPatient,
                P.SMSAlertNum,
                CASE WHEN P.LanguagePreference IS NOT NULL THEN P.LanguagePreference ELSE 'French' END AS LanguagePreference,
                MV.Status,
                LTRIM(RTRIM(CR.ResourceName)) AS ResourceName,
                MV.ScheduledDateTime AS ScheduledStartTime,
                HOUR(MV.ScheduledDateTime) AS ScheduledStartTime_hh,
                MINUTE(MV.ScheduledDateTime) AS ScheduledStartTime_mm,
                TIMESTAMPDIFF(MINUTE,NOW(), MV.ScheduledDateTime) AS TimeRemaining,
                TIMESTAMPDIFF(MINUTE,PL.ArrivalDateTime,NOW()) AS WaitTime,
                HOUR(PL.ArrivalDateTime) AS ArrivalDateTime_hh,
                MINUTE(PL.ArrivalDateTime) AS ArrivalDateTime_mm,
                PL.CheckinVenueName AS VenueId,
                MV.AppointSys AS CheckinSystem,
                DATE_FORMAT(P.DateOfBirth,'%b %d') AS Birthday,
                TIMESTAMPDIFF(YEAR,P.DateOfBirth,CURDATE()) AS Age,
                P.Sex,
                PM.Date AS WeightDate,
                PM.Weight,
                PM.Height,
                PM.BSA,
                (SELECT DATE_FORMAT(MAX(ReviewTimestamp),'%Y-%m-%d %H:%i') FROM TEMP_PatientQuestionnaireReview WHERE PatientSer = P.PatientSerNum) AS LastQuestionnaireReview
            FROM
                MediVisitAppointmentList MV
                INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
                INNER JOIN SpecialityGroup SG ON SG.SpecialityGroupId = CR.SpecialityGroupId
                INNER JOIN Patient P ON P.PatientSerNum = MV.PatientSerNum
                INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                    AND PH.HospitalId = SG.HospitalId
                    AND PH.Active = 1
                INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
                LEFT JOIN PatientLocation PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
                LEFT JOIN PatientMeasurement PM ON PM.PatientMeasurementSer =
                    (
                        SELECT
                            PMM.PatientMeasurementSer
                        FROM
                            PatientMeasurement PMM
                        WHERE
                            PMM.PatientSer = P.PatientSerNum
                            AND PMM.Date BETWEEN DATE_SUB(CURDATE(), INTERVAL 21 DAY) AND NOW()
                        ORDER BY
                            PMM.Date DESC,
                            PMM.Time DESC
                        LIMIT 1
                    )
            WHERE
                MV.ScheduledDate = CURDATE()
                AND MV.Status IN ('Open','Completed','In Progress')
            ORDER BY
                P.LastName,
                MV.ScheduledDateTime,
                MV.AppointmentSerNum
        ");
        $query->execute();

        return array_map(fn($x) => [
            "Age"                     => (int) $x["Age"],
            "AppointmentId"           => (int) $x["AppointmentId"],
            "AppointmentName"         => $x["AppointmentName"],
            "ArrivalDateTime"         => $x["ArrivalDateTime"] ?? null,
            "ArrivalDateTime_hh"      => ($x["ArrivalDateTime_hh"] ?? null) ? (int) $x["ArrivalDateTime_hh"] : null,
            "ArrivalDateTime_mm"      => ($x["ArrivalDateTime_mm"] ?? null) ? (int) $x["ArrivalDateTime_mm"] : null,
            "Birthday"                => $x["Birthday"],
            "BSA"                     => ($x["BSA"] ?? null) ? (float) $x["BSA"] : null,
            "CheckinSystem"           => $x["CheckinSystem"],
            "FirstName"               => $x["FirstName"],
            "Height"                  => ($x["Height"] ?? null) ? (float) $x["Height"]: null,
            "LanguagePreference"      => $x["LanguagePreference"],
            "LastName"                => $x["LastName"],
            "LastQuestionnaireReview" => $x["LastQuestionnaireReview"] ?? null,
            "Mrn"                     => $x["Mrn"],
            "OpalPatient"             => (int) $x["OpalPatient"],
            "PatientId"               => (int) $x["PatientId"],
            "ResourceName"            => $x["ResourceName"],
            "ScheduledStartTime"      => $x["ScheduledStartTime"],
            "ScheduledStartTime_hh"   => (int) $x["ScheduledStartTime_hh"],
            "ScheduledStartTime_mm"   => (int) $x["ScheduledStartTime_mm"],
            "Sex"                     => $x["Sex"],
            "Site"                    => $x["Site"],
            "SMSAlertNum"             => $x["SMSAlertNum"],
            "SourceId"                => $x["SourceId"],
            "SpecialityGroupId"       => (int) $x["SpecialityGroupId"],
            "Status"                  => $x["Status"],
            "TimeRemaining"           => (int) $x["TimeRemaining"],
            "VenueId"                 => $x["VenueId"] ?? null,
            "WaitTime"                => (int) $x["WaitTime"],
            "Weight"                  => ($x["Weight"] ?? null) ? (float) $x["Weight"] : null,
            "WeightDate"              => $x["WeightDate"] ?? null,
        ], $query->fetchAll());
    }
}
