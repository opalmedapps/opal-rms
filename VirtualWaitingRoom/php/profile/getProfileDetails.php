<?php declare(strict_types=1);
//script to get the page settings for a profile

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Config;
use Orms\Database;

//get webpage parameters
$profileId = Encoding::utf8_decode_recursive($_GET["profileId"]);
$clinicalArea = Encoding::utf8_decode_recursive($_GET["clinicalArea"] ?? NULL);

$json = []; //output array

$resources = [];
$appointments = [];
$intermediateVenues = [];
$treatmentVenues = [];
$examRooms = [];

//connect to db
$dbh = Database::getOrmsConnection();

//==================================
//get profile
//==================================
$queryProfile = $dbh->prepare("
    SELECT
        Profile.ProfileSer,
        Profile.ProfileId,
        Profile.Category,
        Profile.Speciality,
        Profile.ClinicalArea
    FROM
        Profile
    WHERE
        Profile.ProfileId = ?"
);
//process results
$queryProfile->execute([$profileId]);

$profile = $queryProfile->fetchAll()[0] ?? NULL;

if($profile === NULL) {
    echo "{}";
    exit;
}

$json['ProfileSer']           = $profile['ProfileSer'];
$json['ProfileId']            = $profile['ProfileId'];
$json['Category']             = $profile['Category'];
$json['Speciality']           = $profile['Speciality'];
$json['ClinicalArea']         = $profile['ClinicalArea'];
$json['WaitingRoom']          = "WAITING ROOM";
$json['IntermediateVenues']   = [];
$json['TreatmentVenues']      = [];
$json['Resources']            = [];
$json['ExamRooms']            = [];
$json['Appointments']         = [];
$json['ColumnsDisplayed']     = [];

//if there profile has no assigned clinical area, use the one provided by the user
if($clinicalArea) $json['ClinicalArea'] = $clinicalArea;

//if a location is specified, set the associated waiting room
$json['WaitingRoom'] = strtoupper($json['ClinicalArea']) ." WAITING ROOM";

//set the load order of the appointments on the vwr page depending the category
$json['sortOrder'] = 'LastName'; //default
if($json['Category'] == 'PAB' or $json['Category'] == 'Treatment Machine' or $json['Category'] == 'Physician')
{
    $json['sortOrder'] = ['ScheduledStartTime_hh','ScheduledStartTime_mm'];
}
else if($json['Category'] == 'Pharmacy')
{
    $json['sortOrder'] = 'LastName';
}

//==========================================================
//get the profile columns
//==========================================================
$queryColumns = $dbh->prepare( "
    SELECT
        ProfileColumnDefinition.ColumnName,
        ProfileColumnDefinition.DisplayName,
        ProfileColumnDefinition.Glyphicon,
        ProfileColumns.Position
    FROM
        ProfileColumns
        INNER JOIN ProfileColumnDefinition ON ProfileColumnDefinition.ProfileColumnDefinitionSer = ProfileColumns.ProfileColumnDefinitionSer
    WHERE
        ProfileColumns.ProfileSer = $json[ProfileSer]
        AND ProfileColumns.Position >= 0
        AND ProfileColumns.Active = 1
    ORDER BY
        ProfileColumns.Position
");
$queryColumns->execute();

$json['ColumnsDisplayed'] = $queryColumns->fetchAll();

//=======================================================
//next get the profile options
//=======================================================
$queryOptions = $dbh->prepare("
    SELECT
        ProfileOptions.Options,
        ProfileOptions.Type
    FROM
        ProfileOptions
    WHERE
        ProfileOptions.ProfileSer = '$json[ProfileSer]'
    ORDER BY ProfileOptions.Options"
);
$queryOptions->execute();

foreach($queryOptions->fetchAll() as $row)
{
    if($row['Type'] == 'Appointment') {$appointments[] = $row['Options'];}
    else if($row['Type'] == 'IntermediateVenue')
    {
        $intermediateVenues[] = $row['Options'];
        $json['IntermediateVenues'][] = $row;
    }
    else if($row['Type'] == 'TreatmentVenue')
    {
        $treatmentVenues[] = $row['Options'];
        $json['TreatmentVenues'][] = $row;
    }
    else if($row['Type'] == 'ExamRoom')
    {
        $examRooms[] = $row['Options'];
        $json['ExamRooms'][] = $row;
    }
    else if($row['Type'] == 'Resource') {$resources[] = $row['Options'];}
}

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

//get page settings
$configs = Config::getApplicationSettings();

$json["FirebaseUrl"] = $configs->system->firebaseUrl;
$json["FirebaseSecret"] = $configs->system->firebaseSecret;

$json["CheckInFile"] = $configs->environment->baseUrl ."/VirtualWaitingRoom/checkin/{$profile['Speciality']}.json";

//encode and return the json object
$json = Encoding::utf8_encode_recursive($json);
echo json_encode($json,JSON_NUMERIC_CHECK);

?>
