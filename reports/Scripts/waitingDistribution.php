<?php declare(strict_types = 1);
#---------------------------------------------------------------------------------------------------------------
# This script create a distribution of patient wait times in the specified date range for each doctor and clinic in the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\Database;

#parse input parameters
$sDate        = $_GET["sDate"] ?? NULL; $sDate .= " 00:00:00";
$eDate        = $_GET["eDate"] ?? NULL; $eDate .= " 23:59:59";
$speciality   = $_GET["mode"] ?? NULL; #speciality of appointments to find
$method       = $_GET["method"] ?? NULL; #normally blank, but if it is set to 'scheduled', the report will find the time difference from when the patient was called to their appointment scheduled time

#connect to the database and extract data
$dbh = Database::getOrmsConnection();

#get a list of patients who waited in a waiting room
$sql = "
    SELECT DISTINCT
        CR.ResourceCode,
        CR.ResourceName,
        MV.ScheduledDateTime,
        PL.AppointmentSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        PL.PatientLocationRevCount,
        CAST(PL.ArrivalDateTime AS DATE) AS Date,
        PL.ArrivalDateTime AS Arrival,
        PL.DichargeThisLocationDateTime AS Discharge,
        PL.CheckinVenueName
    FROM
        MediVisitAppointmentList MV
        INNER JOIN Patient ON Patient.PatientSerNum = MV.PatientSerNum
        INNER JOIN PatientLocationMH PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
            AND (
                PL.CheckinVenueName LIKE '%Waiting%'
                OR PL.CheckinVenueName LIKE '%WAITING%'
            )
            AND PL.ArrivalDateTime BETWEEN :sDate AND :eDate
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
            AND CR.Speciality = :spec
    ORDER BY
        Patient.LastName,
        Patient.FirstName,
        PL.AppointmentSerNum,
        PL.ArrivalDateTime";
$query = $dbh->prepare($sql);
$query->execute([
    ":sDate" => $sDate,
    "eDate"  => $eDate,
    ":spec"  => $speciality
]);

$resources = array_map(function($checkIn) use ($method) {
    #if the method is set to 'scheduled', we instead calculate the time between when the patient was called (ie when they checkout of the waiting room) to their appointment scheduled start
    if($method === "scheduled") $checkIn["Arrival"] = $checkIn["ScheduledDateTime"];

    $checkIn["CheckinVenueName"] = preg_replace("/ WAITING ROOM| Waiting Room/","",$checkIn["CheckinVenueName"]);

    $start = new DateTime($checkIn["Arrival"] ?? "");
    $end = new DateTime($checkIn["Discharge"] ?? "");

    $checkIn["Arrival"] = $start->format("H:i:s");
    $checkIn["Discharge"] = $end->format("H:i:s");
    $checkIn["waitTime"] = $end->getTimestamp() - $start->getTimestamp();

    #keys must be strings to use array_merge later
    return [
            $checkIn["ResourceCode"] => [
                "name"                          => [$checkIn["ResourceName"]],
                "!$checkIn[AppointmentSerNum]"  => [
                    "fname"     => [$checkIn["FirstName"]],
                    "lname"     => [$checkIn["LastName"]],
                    "pID"       => [$checkIn["PatientId"]],
                    "room"      => [$checkIn["CheckinVenueName"]],
                    "day"       => [$checkIn["Date"]],
                    "waitTime"  => [$checkIn["waitTime"]],
                    "arrival"   => [$checkIn["Arrival"]],
                    "discharge" => [$checkIn["Discharge"]],
                ]
            ]
    ];
},$query->fetchAll());

#combine all entries that have the same resource and appointment sernum
$resources = array_merge_recursive(...$resources);

#extract the appointments entries from the inner array
#also perform some processing and construct the data needed for highcharts on the front-end
$resources = array_map(function($resource) {

    $patientData = array_diff_key($resource,["name"=>""]);
    $patientData = array_map(function($patient) {
        #calculate the total wait time of the patient
        $waitTime = array_sum($patient["waitTime"] ?? []);

        #if the patient has been waiting too long, it is likely that the patient was never checked out so the check out is not valid
        if($waitTime > 15 * 3600) $patient["discharge"] = ["N/A"];

        #cap the patient's wait time to 8.1 hours so they appear in the chart on the front-end
        if($waitTime > 8 * 3600) $waitTime = 8.1 * 3600;

        $waitPeriod = array_map(function($arr,$dis) {
            return "$arr - $dis";
        },$patient["arrival"] ?? [],$patient["discharge"] ?? []);
        $waitPeriod = implode(", ",$waitPeriod);

        return [
            "fname"         => $patient["fname"][0] ?? NULL,
            "lname"         => $patient["lname"][0] ?? NULL,
            "pID"           => $patient["pID"][0] ?? NULL,
            "room"          => $patient["room"][0] ?? NULL,
            "day"           => $patient["day"][0] ?? NULL,
            "waitTime"      => $waitTime,
            "waitPeriod"    => $waitPeriod,
        ];
    },$patientData);
    $patientData = array_values($patientData);

    #sum the number of patients in each interval of half an hour
    $graphData = array_reduce($patientData,function($intervals,$patient) {
        $interval = 0.5 * (int) ($patient["waitTime"] / (30 * 60));
        if($interval <= 0 && $patient["waitTime"] < 0) $interval -= 0.5;

        if(!array_key_exists("$interval",$intervals)) $intervals["$interval"] = 0;
        $intervals["$interval"]++;

        return $intervals;
    },[]);
    array_walk($graphData,function(&$val,$key) {
        $val = [(float)$key,$val];
    });
    $graphData = array_values($graphData);
    sort($graphData);

    return [
        "name"        => $resource["name"][0] ?? NULL,
        "patientData" => $patientData,
        "graphData"   => $graphData,
    ];
},$resources);

#sort the resources in alphabetical order
$resources = array_values($resources);
usort($resources,function($x,$y) {
    return $x["name"] <=> $y["name"];
});

#create a "Summary" resource containing all the data from all the resources
$summary = [];
foreach($resources as $val) {
    $summary[] = $val;
}
$summary = array_merge_recursive(...$summary);
$summary["name"] = "Total";

#sum all graph data for the same time categories
$summedGraphData = [];
foreach($summary["graphData"] ?? [] as $data) {
    #data has format [category,# of patients]
    if(!array_key_exists("$data[0]",$summedGraphData)) $summedGraphData["$data[0]"] = 0;
    $summedGraphData["$data[0]"] += $data[1];
}

array_walk($summedGraphData,function(&$val,$key) {
    $val = [(float)$key,$val];
});
$summedGraphData = array_values($summedGraphData);
sort($summedGraphData);

$summary["graphData"] = $summedGraphData;
array_unshift($resources,$summary);

#calculate mean and std
foreach($resources as &$res)
{
    $patientWaitTimes = array_map(function($val) {
        return $val["waitTime"];
    },$res["patientData"] ?? []);

    #filter appointments that were auto-completed
    $patientWaitTimes = array_filter($patientWaitTimes,function($val){
        return ($val < 8.1*3600);
    });

    $res["mean"] = count($patientWaitTimes) !== 0 ? array_sum($patientWaitTimes)/count($patientWaitTimes) : 0;
    $res["std"] = sd($patientWaitTimes);
}

$resources = utf8_encode_recursive($resources);
echo json_encode($resources);

#functions

// Function to calculate square of value - mean
function sd_square(float $x,float $mean): float
{
    return pow($x-$mean,2);
}

// Function to calculate standard deviation (uses sd_square)
/**
 *
 * @param float[] $array
 */
function sd(array $array): float|NULL
{
    if(count($array) <= 1) return NULL;
    // square root of sum of squares devided by N-1
    return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
}


?>
