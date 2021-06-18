<?php declare(strict_types=1);
//script to get the page settings for a profile

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Config;
use Orms\Database;

//get webpage parameters
$profileId = $_GET["profileId"];
$clinicHubId = $_GET["clinicHubId"];

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
        ProfileSer,
        ProfileId,
        Category,
        SpecialityGroupId AS Speciality,
        (SELECT ClinicHubName FROM ClinicHub WHERE ClinicHubId = :chId) AS ClinicalArea
    FROM
        Profile
    WHERE
        ProfileId = :proId
");
//process results
$queryProfile->execute([
    ":proId" => $profileId,
    ":chId"  => $clinicHubId
]);

$profile = $queryProfile->fetchAll()[0] ?? NULL;

/** @phpstan-ignore-next-line */
if($profile === NULL || $profile["ClinicalArea"] === NULL) {
    echo "{}";
    exit;
}

$json['ProfileSer']           = $profile['ProfileSer'];
$json['ProfileId']            = $profile['ProfileId'];
$json['Category']             = $profile['Category'];
$json['Speciality']           = $profile['Speciality'];
$json['ClinicalArea']         = $profile['ClinicalArea'];
$json["ClinicHubId"]          = $clinicHubId;
$json['WaitingRoom']          = "WAITING ROOM";
$json['IntermediateVenues']   = [];
$json['TreatmentVenues']      = [];
$json['Resources']            = [];
$json['ExamRooms']            = [];
$json['Appointments']         = [];
$json['ColumnsDisplayed']     = [];

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
        PCD.ColumnName,
        PCD.DisplayName,
        PCD.Glyphicon,
        PC.Position
    FROM
        ProfileColumns PC
        INNER JOIN ProfileColumnDefinition PCD ON PCD.ProfileColumnDefinitionSer = PC.ProfileColumnDefinitionSer
    WHERE
        PC.ProfileSer = ?
        AND PC.Position >= 0
        AND PC.Active = 1
    ORDER BY
        PC.Position
");
$queryColumns->execute([$json["ProfileSer"]]);

$json['ColumnsDisplayed'] = $queryColumns->fetchAll();

//=======================================================
//next get the profile options
//=======================================================
$queryOptions = $dbh->prepare("
    SELECT
        Options,
        Type
    FROM
        ProfileOptions
    WHERE
        ProfileSer = ?
    ORDER BY
        Options
");
$queryOptions->execute([$json["ProfileSer"]]);

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

$json["FirebaseUrl"] = $configs->environment->firebaseUrl;
$json["FirebaseSecret"] = $configs->environment->firebaseSecret;

$json["CheckInFile"] = $configs->environment->baseUrl ."/VirtualWaitingRoom/checkin/{$profile['Speciality']}.json";

//encode and return the json object
$json = Encoding::utf8_encode_recursive($json);
echo json_encode($json,JSON_NUMERIC_CHECK);
