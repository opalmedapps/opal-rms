<?php
//script to insert/create a profile in the WRM database

require("loadConfigs.php");

//get webpage parameters
$postData = file_get_contents("php://input");
$postData = json_decode($postData,true);
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
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//if the profile is a new one, we have to create it
//if not, we just update an existing profile with new information

if($profile['ProfileSer'] == -1)
{
	$sqlCreateProfile = "CALL SetupProfile('$profile[ProfileId]','$profile[Speciality]','$profile[ClinicalArea]');";

	$queryCreateProfile = $dbWRM->query($sqlCreateProfile);

	//the subroutine returns the new profile's serial so we update ours
	$row = $queryCreateProfile->fetch(PDO::FETCH_NUM);
	$profile['ProfileSer'] = $row[0];

	$dbWRM = null; //close the db for the next part
}

//due to a PDO bug, two stored procedures cannot be used at the same time so we close and reopen the connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//insert the profile properties
$sqlProperties = "
	UPDATE Profile
	SET
		ProfileId = '$profile[ProfileId]',
		Category = '$profile[Category]',
		Speciality = '$profile[Speciality]',
		ClinicalArea = '$profile[ClinicalArea]',
		FetchResourcesFromVenues = $profile[FetchResourcesFromVenues],
		FetchResourcesFromClinics = $profile[FetchResourcesFromClinics],
		ShowCheckedOutAppointments = $profile[ShowCheckedOutAppointments]
	WHERE Profile.ProfileSer = $profile[ProfileSer]";

$queryProperties = $dbWRM->exec($sqlProperties);

$dbWRM = null; //close the db for the next part

//insert the profile options

//due to a PDO bug, two stored procedures cannot be used at the same time so we close and reopen the connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//first create an option string to give to the query
$optionNameString = implode("|||",array_map(function ($obj)
	{
		return $obj['Name'];
	},array_merge($profile['ExamRooms'],$profile['IntermediateVenues'],$profile['TreatmentVenues'],$profile['Resources'],$profile['Clinics'])));
$optionTypeString = implode("|||",array_map(function ($obj)
	{
		return $obj['Type'];
	},array_merge($profile['ExamRooms'],$profile['IntermediateVenues'],$profile['TreatmentVenues'],$profile['Resources'],$profile['Clinics'])));

if($optionNameString != '')
{
	$sqlOptions = "CALL UpdateProfileOptions('$profile[ProfileId]','$optionNameString','$optionTypeString')";

	$queryOptions = $dbWRM->exec($sqlOptions);

	$dbWRM = null; //close the db for the next part
}

//due to a PDO bug, two stored procedures cannot be used at the same time so we close and reopen the connection
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//insert the profile columns
$columnNameString = implode("|||",array_map(function ($obj)
	{
		return $obj['ColumnName'];
	},$columns));


if($columnNameString != '')
{
	$sqlColumns = "CALL UpdateProfileColumns('$profile[ProfileId]','$columnNameString')";

	$queryColumns = $dbWRM->exec($sqlColumns);
}

$dbWRM = null;

?>
