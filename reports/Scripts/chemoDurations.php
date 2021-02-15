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
        P.PatientId,
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
    WHERE
        P.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC')
        AND P.PatientId NOT LIKE 'Opal%'
    ORDER BY MV.ScheduledDateTime, P.PatientId
");
$query->execute([
    ":sDate" => $sDate,
    ":eDate" => $eDate
]);

echo json_encode($query->fetchAll());

?>
