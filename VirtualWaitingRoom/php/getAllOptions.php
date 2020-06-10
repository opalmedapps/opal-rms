<?php
//====================================================================================
// php code to query the MySQL (for Medivisit) databases and
// extract the list of options, which includes resources, appointments, and rooms
//====================================================================================
require("loadConfigs.php");

//get webpage parameters
$speciality = $_GET["speciality"];

$json = []; //json object to be returned

$resources = [];
$appointments = [];
$intermediateVenues = [];
$treatmentVenues = [];
$examRooms = [];
$clinics = [];

//==================================================================
// Connect to WRM database
//==================================================================
#connect to the WRM database
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//==================================================================
// Get Resources associated with appointments (also called clinics)
//==================================================================

//get the WRM resources
$query2 = $dbWRM->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(MediVisitAppointmentList.ResourceDescription)) AS ResourceDescription
    FROM
        MediVisitAppointmentList
    INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
        AND ClinicResources.Speciality = ?
");
$query2->execute([$speciality]);

// Process results
while($row = $query2->fetch(PDO::FETCH_ASSOC))
{
	$resources[] = $row['ResourceDescription'];
}

//================================================================================
// Get all venues and exam rooms (basically, get all possible rooms)
//================================================================================

//get all exam rooms for the speciality
$query3 = $dbWRM->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(ExamRoom.AriaVenueId)) AS AriaVenueId
    FROM
        ExamRoom
    WHERE ExamRoom.Level = ?
");
$query3->execute([$speciality]);

// Process results
while($row = $query3->fetch(PDO::FETCH_ASSOC))
{
	$examRooms[] = $row['AriaVenueId'];
}

//get all possible intermediate venues for the speciality
$query4 = $dbWRM->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(IntermediateVenue.AriaVenueId)) AS AriaVenueId
    FROM
        IntermediateVenue
    WHERE
        IntermediateVenue.Level = ?
");
$query4->execute([$speciality]);

// Process results
while($row = $query4->fetch(PDO::FETCH_ASSOC))
{
    if(preg_match('/(TX AREA|RT TX ROOM)/',$row['AriaVenueId']))
    {
        $treatmentVenues[] = $row['AriaVenueId'];

    }
    else
    {
        $intermediateVenues[] = $row['AriaVenueId'];
    }
}


//================================================================================
// Get all appointment names
//================================================================================

//get all appointments from WRM
$query6 = $dbWRM->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(MediVisitAppointmentList.AppointmentCode)) AS AppointmentCode
    FROM
        MediVisitAppointmentList
        INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
            AND ClinicResources.Speciality = ?"
);
$query6->execute([$speciality]);

//process results
while($row = $query6->fetch(PDO::FETCH_ASSOC))
{
	$appointments[] = $row['AppointmentCode'];
}

//get all clinics
$sql7 = "
	SELECT DISTINCT
		LTRIM(RTRIM(ClinicSchedule.ClinicName)) AS ClinicName
	FROM
		ClinicSchedule
		INNER JOIN ClinicResources ON ClinicResources.ClinicScheduleSerNum = ClinicSchedule.ClinicScheduleSerNum
";

$query7 = $dbWRM->query($sql7);

//process results
while($row = $query7->fetch(PDO::FETCH_ASSOC))
{
	$clinics[] = $row['ClinicName'];
}

//====================================================
//organize json array
//====================================================
//foreach($resources as &$val) {$val = utf8_encode($val);}
$resources = array_filter(array_unique($resources));
sort($resources);

//foreach($intermediateVenues as &$val) {$val = utf8_encode($val);}
$intermediateVenues = array_filter(array_unique($intermediateVenues));
sort($intermediateVenues);

//foreach($treatmentVenues as &$val) {$val = utf8_encode($val);}
$treatmentVenues = array_filter(array_unique($treatmentVenues));
sort($treatmentVenues);

//foreach($examRooms as &$val) {$val = utf8_encode($val);}
$examRooms = array_filter(array_unique($examRooms));
sort($examRooms);

//foreach($clinics as &$val) {$val = utf8_encode($val);}
$clinics = array_filter(array_unique($clinics));
sort($clinics);

//add the type to each element of the arrays and then add them to the json return object
$json['Resources'] = [];
foreach($resources as $val)
{
	$json['Resources'][] = ['Name'=>$val,'Type'=>'Resource'];
}

$json['Appointments'] = [];
foreach($appointments as $val)
{
	$json['Appointments'][] = ['Name'=>$val,'Type'=>'Appointment'];
}

$json['Locations'] = [];
foreach($intermediateVenues as $val)
{
	$json['Locations'][] = ['Name'=>$val,'Type'=>'IntermediateVenue'];
}
foreach($treatmentVenues as $val)
{
	$json['Locations'][] = ['Name'=>$val,'Type'=>'TreatmentVenue'];
}
foreach($examRooms as $val)
{
	$json['Locations'][] = ['Name'=>$val,'Type'=>'ExamRoom'];
}

$json['Clinics'] = [];
foreach($clinics as $val)
{
	$json['Clinics'][] = ['Name'=>$val,'Type'=>'Clinic'];
}

//return results in json format

$json = utf8_encode_recursive($json);
echo json_encode($json);

?>
