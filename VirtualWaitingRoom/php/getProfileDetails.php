<?php
//script to get the page settings for a profile

require("loadConfigs.php");

//get webpage parameters
$profileId = utf8_decode_recursive($_GET["profileId"]);
$clinicalArea = utf8_decode_recursive($_GET["clinicalArea"]);
$ignoreAutoResources = $_GET['ignoreAutoResources'];

$json = []; //output array

$resources = [];
$appointments = [];
$intermediateVenues = [];
$treatmentVenues = [];
$examRooms = [];
$clinics = [];

//connect to db
$dbWRM = new PDO(WRM_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

//==================================
//get profile
//==================================
$queryProfile = $dbWRM->prepare("
	SELECT
		Profile.ProfileSer,
		Profile.ProfileId,
		Profile.Category,
		Profile.Speciality,
		Profile.ClinicalArea,
		Profile.FetchResourcesFromVenues,
		Profile.FetchResourcesFromClinics,
		Profile.ShowCheckedOutAppointments
	FROM
		Profile
	WHERE
		Profile.ProfileId = ?"
);
//process results
$queryProfile->execute([$profileId]);

$row = $queryProfile->fetchAll(PDO::FETCH_ASSOC)[0] ?? NULL;

if($row === NULL) {
    echo "{}";
    exit;
}

$json['ProfileSer'] = $row['ProfileSer'];
$json['ProfileId'] = $row['ProfileId'];
$json['Category'] = $row['Category'];
$json['Speciality'] = $row['Speciality'];
$json['ClinicalArea'] = $row['ClinicalArea'];
$json['FetchResourcesFromVenues'] = $row['FetchResourcesFromVenues'];
$json['FetchResourcesFromClinics'] = $row['FetchResourcesFromClinics'];
$json['ShowCheckedOutAppointments'] = $row['ShowCheckedOutAppointments'];
$json['WaitingRoom'] = "WAITING ROOM";
$json['IntermediateVenues'] = [];
$json['TreatmentVenues'] = [];
$json['Resources'] = [];
$json['ExamRooms'] = [];
$json['Clinics'] = [];
$json['Appointments'] = [];
$json['ColumnsDisplayed'] = [];

//if there profile has no assigned clinical area, use the one provided by the user
//if the user provided a clinical area, use it
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
$sqlColumns = "
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
		ProfileColumns.Position";
//process results
$queryColumns = $dbWRM->query($sqlColumns);

while($row = $queryColumns->fetch(PDO::FETCH_ASSOC))
{
	$json['ColumnsDisplayed'][] = $row;
}

//=======================================================
//next get the profile options
//=======================================================
$sqlOptions = "
	SELECT
		ProfileOptions.Options,
		ProfileOptions.Type
	FROM
		ProfileOptions
	WHERE
		ProfileOptions.ProfileSer = '$json[ProfileSer]'
	ORDER BY ProfileOptions.Options";

//process results
$queryOptions = $dbWRM->query($sqlOptions);

while($row = $queryOptions->fetch(PDO::FETCH_ASSOC))
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
	else if($row['Type'] == 'Clinic')
	{
		$clinics[] = $row['Options'];
	}
}

//=============================================================
//Get additional settings
//=============================================================
if($ignoreAutoResources != 1)
{
	//we only get exam rooms/resources from clinics that are "active"
	$rightNow = date("D-H");
	$rightNow = explode("-",$rightNow); //index 0 is the week day, index 1 the time (hour)

	if($rightNow[1] < 12) {$rightNow[1] = 'AM';}
	else {$rightNow[1] = 'PM';}

	//if the profile has an intermediate venue (probably because its a profile meant to be used at a specific location), we get all associated resources and exam rooms
	//however, we only do this if the profile autofetch feature is on
	if($json['FetchResourcesFromVenues'] and ($intermediateVenues or $treatmentVenues))
	{
		$interVenueList = implode("','",$intermediateVenues+$treatmentVenues);

		//get associated exam rooms from venues
		$sqlExamRooms = "
			SELECT DISTINCT
				ExamRoom.AriaVenueId
			FROM
				IntermediateVenue
				INNER JOIN ExamRoom ON ExamRoom.IntermediateVenueSerNum = IntermediateVenue.IntermediateVenueSerNum
			WHERE
				IntermediateVenue.AriaVenueId IN ('$interVenueList')";

		$queryExamRooms = $dbWRM->query($sqlExamRooms);

		while($row = $queryExamRooms->fetch(PDO::FETCH_ASSOC))
		{
			$examRooms[] = $row['AriaVenueId'];
		}

		//get associated resources from venues

		//in the database, all most resources for the TX areas are associated with TX AREA A even though they should also be associated to other areas
		//so we have to convert some of the intermediate venues
		$convertedVenues = [];
		foreach($intermediateVenues+$treatmentVenues as $val)
		{
			$convertedVenues[] = $val;
		}

		foreach($convertedVenues as &$val)
		{
			if($val == 'TX AREA B'
				|| $val == 'TX AREA C'
				|| $val == 'TX AREA D'
				|| $val == 'TX AREA E'
				|| $val == 'TX AREA F'
				|| $val == 'TX AREA G'
				|| $val == 'TX AREA H'
				|| $val == 'TX AREA U'
				|| $val == 'pharmacy'
				|| $val == 'PodA'
				|| $val == 'PodB'
				|| $val == 'PodC')
			{$val = 'TX AREA A';}
		}

		$sqlResources = "
			SELECT DISTINCT
				ClinicResources.ResourceName
			FROM
				ClinicResources
				INNER JOIN ClinicSchedule ON ClinicSchedule.ClinicScheduleSerNum = ClinicResources.ClinicScheduleSerNum
					AND ClinicSchedule.Day = '$rightNow[0]'
					AND ClinicSchedule.AMPM = '$rightNow[1]'
				INNER JOIN ExamRoom ON ExamRoom.ExamRoomSerNum = ClinicSchedule.ExamRoomSerNum
				INNER JOIN IntermediateVenue ON IntermediateVenue.IntermediateVenueSerNum = ExamRoom.IntermediateVenueSerNum
					AND IntermediateVenue.AriaVenueId IN ('". implode("','",$convertedVenues) ."')
			WHERE
				ClinicResources.Speciality = '$json[Speciality]'";

		$queryResources = $dbWRM->query($sqlResources);

		while($row = $queryResources->fetch(PDO::FETCH_ASSOC))
		{
			$resources[] = $row['ResourceName'];
		}
	}

	//same thing for clinics
	//if the FetchResourcesFromClinic is on we get exam rooms and resources associated with the clinics
	if($json['FetchResourcesFromClinics'] and $clinics)
	{
		//get associated exam rooms from clinics
		$sqlExamRooms = "
			SELECT DISTINCT
				ExamRoom.AriaVenueId
			FROM
				ClinicSchedule
				INNER JOIN ExamRoom ON ExamRoom.ExamRoomSerNum = ClinicSchedule.ExamRoomSerNum
			WHERE
				ClinicSchedule.ClinicName IN ('". implode("','",$clinics) ."')
				AND ClinicSchedule.Day = '$rightNow[0]'
				AND ClinicSchedule.AMPM = '$rightNow[1]'";

		$queryExamRooms = $dbWRM->query($sqlExamRooms);

		while($row = $queryExamRooms->fetch(PDO::FETCH_ASSOC))
		{
			$examRooms[] = $row['AriaVenueId'];
		}

		//get associated resources from clinics
		$sqlResources = "
			SELECT DISTINCT
				ClinicResources.ResourceName
			FROM
				ClinicSchedule
				INNER JOIN ClinicResources ON ClinicResources.ClinicScheduleSerNum = ClinicSchedule.ClinicScheduleSerNum
			WHERE
				ClinicSchedule.ClinicName IN ('". implode("','",$clinics) ."')
				AND ClinicSchedule.Day = '$rightNow[0]'
				AND ClinicSchedule.AMPM = '$rightNow[1]'";

		$queryResources = $dbWRM->query($sqlResources);

		while($row = $queryResources->fetch(PDO::FETCH_ASSOC))
		{
			$resources[] = $row['ResourceName'];
		}
	}


	//filter, uniquify and sort arrays
	$resources = array_filter(array_unique($resources));
	sort($resources);

	$intermediateVenues = array_filter(array_unique($intermediateVenues));
	sort($intermediateVenues);

	$treatmentVenues = array_filter(array_unique($treatmentVenues));
	sort($treatmentVenues);

	$examRooms = array_filter(array_unique($examRooms));
	sort($examRooms);

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
foreach($clinics as $val)
{
	$json['Clinics'][] = ['Name'=>$val,'Type'=>'Clinic'];
}

//get firebase settings
$json["FirebaseUrl"] = FIREBASE_URL;
$json["FirebaseSecret"] = FIREBASE_SECRET;

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json,JSON_NUMERIC_CHECK);

?>
