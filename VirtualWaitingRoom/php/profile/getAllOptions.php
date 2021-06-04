<?php declare(strict_types=1);
//====================================================================================
// php code to query the database and
// extract the list of options, which includes resources, appointments, and rooms
//====================================================================================

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Database;

//get webpage parameters
$speciality = $_GET["speciality"];
$clinicHub  = $_GET["clinicHub"];

$json = []; //json object to be returned

$resources = [];
$appointments = [];
$intermediateVenues = [];
$treatmentVenues = [];
$examRooms = [];

//==================================================================
// Connect to WRM database
//======================s============================================
$dbh = Database::getOrmsConnection();

//==================================================================
// Get Resources associated with appointments (also called clinics)
//==================================================================

//get the WRM resources
$query2 = $dbh->prepare("
    SELECT DISTINCT
        ResourceName
    FROM
        ClinicResources
    WHERE
        SpecialityGroupId = ?
        AND Active = 1
");
$query2->execute([$speciality]);

$resources = array_map(fn($x) => $x["ResourceName"], $query2->fetchAll());

//================================================================================
// Get all venues and exam rooms (basically, get all possible rooms)
//================================================================================

//get all exam rooms for the speciality
$query3 = $dbh->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(AriaVenueId)) AS AriaVenueId
    FROM
        ExamRoom
    WHERE
        ClinicHubId = ?
");
$query3->execute([$clinicHub]);

$examRooms = array_map(fn($x) => $x["AriaVenueId"], $query3->fetchAll());

//get all possible intermediate venues for the speciality
$query4 = $dbh->prepare("
    SELECT DISTINCT
        LTRIM(RTRIM(AriaVenueId)) AS AriaVenueId
    FROM
        IntermediateVenue
    WHERE
        ClinicHubId = ?
");
$query4->execute([$clinicHub]);

// Process results
foreach($query4->fetchAll() as $row)
{
    if(preg_match('/(TX AREA|RT TX ROOM)/',$row['AriaVenueId'])) {
        $treatmentVenues[] = $row['AriaVenueId'];
    }
    else {
        $intermediateVenues[] = $row['AriaVenueId'];
    }
}


//================================================================================
// Get all appointment names
//================================================================================

//get all appointments from WRM
$query6 = $dbh->prepare("
    SELECT DISTINCT
        AppointmentCode
    FROM
        AppointmentCode
    WHERE
        SpecialityGroupId = ?
");
$query6->execute([$speciality]);

$appointments = array_map(fn($x) => $x["AppointmentCode"], $query6->fetchAll());

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

//return results in json format

$json = Encoding::utf8_encode_recursive($json);
echo json_encode($json);
