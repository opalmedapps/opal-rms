<?php declare(strict_types = 1);

// legacy report script refactored from perl

#---------------------------------------------------------------------------------------------------------------
# This script finds all the chemo appointments in specified date range and calculates the time spent inside the first "TX AREA" room the patient was checked in to.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Database;

#------------------------------------------
#parse input parameters
#------------------------------------------
$sDateInit = $_GET["sDate"];
$eDateInit = $_GET["eDate"];

$sDate = $sDateInit ." 00:00:00";
$eDate = $eDateInit ." 23:59:59";

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
$dbh = Database::getOrmsConnection();

#get a list of all chemotherapy appointments in the date range
$query = $dbh->prepare("
    SELECT DISTINCT
        P.LastName,
        P.FirstName,
        PH.MedicalRecordNumber AS Mrn,
        H.HospitalCode AS Site,
        MV.Resource,
        MV.ResourceDescription,
        MV.AppointmentCode,
        MV.ScheduledDateTime,
        MV.Status,
        PL.CheckinVenueName,
        PL.ArrivalDateTime,
        PL.DichargeThisLocationDateTime,
        TIMEDIFF(PL.DichargeThisLocationDateTime,PL.ArrivalDateTime) AS Duration,
        PL.PatientLocationRevCount
    FROM
        Patient P
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
            AND MV.Status = 'Completed'
            AND MV.AppointmentCode LIKE '%CHM%'
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
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
            AND PH.HospitalId = (SELECT DISTINCT CH.HospitalId FROM ClinicHub CH WHERE CH.SpecialityGroup = CR.Speciality)
            AND PH.Active = 1
        INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
    ORDER BY MV.ScheduledDateTime, Site, Mrn
");
$query->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate
]);

echo json_encode($query->fetchAll());

?>
