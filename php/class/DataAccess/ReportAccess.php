<?php declare(strict_types = 1);

namespace Orms\DataAccess;

use DateTime;
use Orms\DataAccess\Database;
use PDOException;

class ReportAccess
{
    /**
     *
     * @return list<array{
     *      name: string,
     *      resource: string
     * }>
     */
    static function getClinicCodes(int $specialityId): array
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT DISTINCT
                ResourceName,
                ResourceCode
            FROM
                ClinicResources
            WHERE
                SpecialityGroupId = ?
            ORDER BY
                ResourceName,
                ResourceCode
        ");
        $query->execute([$specialityId]);

        return array_map(function($x) {
            return [
                "name"      => $x["ResourceName"],
                "resource" =>  $x["ResourceCode"]
            ];
        },$query->fetchAll());
    }

    /**
     * @param string[] $statusFilter
     * @param string[] $codeFilter
     * @return list<array{
     *      fname: string,
     *      lname: string,
     *      mrn: string,
     *      site: string,
     *      ramq: string,
     *      appName: string,
     *      appClinic: string,
     *      appType: string,
     *      appStatus: string,
     *      appDay: string,
     *      appTime: string,
     *      checkin: ?string,
     *      createdToday: bool,
     *      referringPhysician: string,
     *      mediStatus: string
     * }>
     */
    static function getListOfAppointmentsInDateRange(DateTime $startDate,DateTime $endDate,int $specialiyGroupId,array $statusFilter,array $codeFilter): array
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
                MV.ScheduledDate AS ScheduledDate,
                MV.ScheduledTime AS ScheduledTime,
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

        $sqlStringWithStatus = Database::generateBoundedSqlString($sql,":statusFilter:","MV","Status",$statusFilter);
        $sqlStringWithCode = Database::generateBoundedSqlString($sqlStringWithStatus["sqlString"],":codeFilter:","CR","ResourceName",$codeFilter);

        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare($sqlStringWithCode["sqlString"]);
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
                "ramq"                  => $x["InsuranceNumber"],
                "appName"               => $x["ResourceName"],
                "appClinic"             => $x["ResourceCode"],
                "appType"               => $x["AppointmentCode"],
                "appStatus"             => $x["Status"],
                "appDay"                => $x["ScheduledDate"],
                "appTime"               => substr($x["ScheduledTime"],0,-3),
                "checkin"               => $x["ArrivalDateTimePL"] ?? $x["ArrivalDateTimePLM"],
                "createdToday"          => (new DateTime($x["CreationDate"]))->format("Y-m-d") === (new DateTime($x["ScheduledDate"]))->format("Y-m-d"), //"today" refers to the date of the appointment
                "referringPhysician"    => $x["ReferringPhysician"],
                "mediStatus"            => $x["MedivisitStatus"],
            ];
        },$query->fetchAll());

    }

}
