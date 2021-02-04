<?php declare(strict_types=1);
//script to insert/create a profile in the WRM database

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Database;

//get webpage parameters
$postData = getPostContents();
$postData = utf8_decode_recursive($postData);

$profile = $postData[0]['data'];
$columns = $postData[1];

//perform some preprocessing
$profile['ExamRooms'] = [];
$profile['IntermediateVenues'] = [];
$profile['TreatmentVenues'] = [];

foreach($profile['Locations'] as $loc)
{
    if($loc['Type'] == 'ExamRoom') {$profile['ExamRooms'][] = $loc;}
    if($loc['Type'] == 'IntermediateVenue') {$profile['IntermediateVenues'][] = $loc;}
    if($loc['Type'] == 'TreatmentVenue') {$profile['TreatmentVenues'][] = $loc;}
}

//connect to db
$dbh = Database::getOrmsConnection();

//if the profile is a new one, we have to create it
//if not, we just update an existing profile with new information

if($profile['ProfileSer'] == -1)
{
    $queryCreateProfile = $dbh->prepare("CALL SetupProfile(?,?,?);");
    $queryCreateProfile->execute([$profile["ProfileId"],$profile["Speciality"],$profile["ClinicalArea"]]);

    //the subroutine returns the new profile's serial so we update ours
    $row = $queryCreateProfile->fetchAll()[0];

    $queryCreateProfile->closeCursor();
    $profile["ProfileSer"] = $row["@profileSer := ProfileSer"];
}

//insert the profile properties
$queryProperties = $dbh->prepare("
    UPDATE Profile
    SET
        ProfileId = :profileId,
        Category = :category,
        Speciality = :speciality,
        ClinicalArea = :clinicalArea,
        FetchResourcesFromVenues = :fetchResourcesFromVenues,
        FetchResourcesFromClinics = :fetchResourcesFromClinics,
        ShowCheckedOutAppointments = :showCheckedOut
    WHERE Profile.ProfileSer = :profileSer
");
$queryProperties->execute([
    ":profileId"                    => $profile["ProfileId"],
    ":category"                     => $profile["Category"],
    ":speciality"                   => $profile["Speciality"],
    ":clinicalArea"                 => $profile["ClinicalArea"],
    ":fetchResourcesFromVenues"     => $profile["FetchResourcesFromVenues"],
    ":fetchResourcesFromClinics"    => $profile["FetchResourcesFromClinics"],
    ":showCheckedOut"               => $profile["ShowCheckedOutAppointments"],
    ":profileSer"                   => $profile["ProfileSer"]
]);
$queryProperties->closeCursor();

//insert the profile options

//first create an option string to give to the query
$optionNameString = implode("|||",array_map(function ($obj) {
    return $obj['Name'];
},array_merge($profile['ExamRooms'],$profile['IntermediateVenues'],$profile['TreatmentVenues'],$profile['Resources'],$profile['Clinics'])));
$optionTypeString = implode("|||",array_map(function ($obj) {
    return $obj['Type'];
},array_merge($profile['ExamRooms'],$profile['IntermediateVenues'],$profile['TreatmentVenues'],$profile['Resources'],$profile['Clinics'])));

$queryOptions = $dbh->prepare("CALL UpdateProfileOptions(?,?,?)");
$queryOptions->execute([$profile["ProfileId"],$optionNameString,$optionTypeString]);
$queryOptions->closeCursor();

//insert the profile columns
$columnNameString = implode("|||",array_map(function ($obj) {
    return $obj['ColumnName'];
},$columns));

$queryColumns = $dbh->prepare("CALL UpdateProfileColumns(?,?)");
$queryColumns->execute([$profile["ProfileId"],$columnNameString]);
$queryColumns->closeCursor();

?>
