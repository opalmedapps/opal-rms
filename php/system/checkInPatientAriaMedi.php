<?php
//==================================================================================== 
// php code to check a patient into all appointments, 
// regardless of whether in Aria or Medivisit - just needs CheckinVenue and PatientId 
//==================================================================================== 
include_once("classLocation.php");
include_once(CLASS_PATH ."/Config.php");

$link = Config::getDatabaseConnection("ARIA");
$conn = Config::getDatabaseConnection("ORMS");

// Connection to Opal
$connOpal = Config::getDatabaseConnection("OPAL");

$baseURL = Config::getConfigs("path")["BASE_URL"];

// Extract the webpage parameters
$PushNotification = 0; # default of zero
$CheckinVenue 		= $_GET["CheckinVenue"];
$PatientId		= $_GET["PatientId"];
$PushNotification	= $_GET["PushNotification"];
$verbose		= $_GET["verbose"];

if ($verbose) echo "Verbose Mode<br>";

// Check connections
if (!$link) {
    die('Something went wrong while connecting to MSSQL');
}

if (!$conn) {
    die("<br>Connection failed");
} 

//======================================================================================
// Check for upcoming appointments in both Aria and Medivisit for this patient
//======================================================================================
$Today = date("Y-m-d");
$startOfToday = "$Today 00:00:00";
$endOfToday = "$Today 23:59:59";

if ($verbose) echo "startOfToday: $startOfToday, endOfToday: $endOfToday<p>";

############################################################################################
######################################### Aria #############################################
############################################################################################
# We need a union on the query as the appointment is either with an auxiliary or a doctor or a resource 

$sqlAppt = "
	  SELECT DISTINCT 
		Patient.PatientId,
		Patient.FirstName,
		Patient.LastName,
		ScheduledActivity.ScheduledStartTime,
		vv_ActivityLng.Expression1,
		ResourceActivity.ResourceSer,	
		ScheduledActivity.ScheduledActivitySer,
		Auxiliary.AuxiliaryId,
		Resource.ResourceType,	
		DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight 
          FROM  
		variansystem.dbo.Patient Patient,
           	variansystem.dbo.ScheduledActivity ScheduledActivity,
		variansystem.dbo.ActivityInstance ActivityInstance,
		variansystem.dbo.Activity Activity,
		variansystem.dbo.vv_ActivityLng vv_ActivityLng,
		variansystem.dbo.ResourceActivity ResourceActivity,
		variansystem.dbo.Auxiliary Auxiliary, 
		variansystem.dbo.Resource Resource
          WHERE 
            	( ScheduledActivity.ScheduledStartTime 		>= '$startOfToday' )    
            AND	( ScheduledActivity.ScheduledStartTime 		< '$endOfToday' )    
	    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
	    AND ( Patient.PatientId 				= '$PatientId') 
	    AND ( ScheduledActivity.ScheduledActivityCode 	= 'Open')
            AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
            AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
            AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
            AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
	    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FIRST CONSULT')
	    AND ( vv_ActivityLng.Expression1 			!= 'FIRST CONSULT')
	    AND ( 
		  vv_ActivityLng.Expression1 			LIKE '%.EB%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%.BXC%'
		 OR vv_ActivityLng.Expression1 			LIKE '%F-U %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW UP %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW-UP%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Transfusion%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Injection%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Nursing Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Hydration%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CONSULT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Daily Rx%'
		 OR vv_ActivityLng.Expression1 			LIKE 'CHEMOTHERAPY'
		 OR vv_ActivityLng.Expression1 			LIKE '%INTRA%'
		)
	    AND  ResourceActivity.ResourceSer = Auxiliary.ResourceSer 
	    AND  Resource.ResourceSer = Auxiliary.ResourceSer 
	UNION
	  SELECT DISTINCT
		Patient.PatientId,
		Patient.FirstName,
		Patient.LastName,
		ScheduledActivity.ScheduledStartTime,
		vv_ActivityLng.Expression1,
		ResourceActivity.ResourceSer,	
		ScheduledActivity.ScheduledActivitySer,
		Doctor.DoctorId,	
		Resource.ResourceType,	
		DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight 
          FROM  
		variansystem.dbo.Patient Patient,
           	variansystem.dbo.ScheduledActivity ScheduledActivity,
		variansystem.dbo.ActivityInstance ActivityInstance,
		variansystem.dbo.Activity Activity,
		variansystem.dbo.vv_ActivityLng vv_ActivityLng,
		variansystem.dbo.ResourceActivity ResourceActivity,
		variansystem.dbo.Doctor Doctor,
		variansystem.dbo.Resource Resource
          WHERE 
            	( ScheduledActivity.ScheduledStartTime 		>= '$startOfToday' )    
            AND	( ScheduledActivity.ScheduledStartTime 		< '$endOfToday' )    
	    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
	    AND ( Patient.PatientId 				= '$PatientId') 
	    AND ( ScheduledActivity.ScheduledActivityCode 	= 'Open')
            AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
            AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
            AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
            AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
	    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FIRST CONSULT')
	    AND ( vv_ActivityLng.Expression1 			!= 'FIRST CONSULT')
	    AND ( 
		  vv_ActivityLng.Expression1 			LIKE '%.EB%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%.BXC%'
		 OR vv_ActivityLng.Expression1 			LIKE '%F-U %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW UP %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW-UP%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Transfusion%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Injection%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Nursing Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Hydration%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CONSULT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%INTRA%'
		)
	    AND  ResourceActivity.ResourceSer = Doctor.ResourceSer 
	    AND  Resource.ResourceSer = Doctor.ResourceSer 
	UNION
	  SELECT DISTINCT 
		Patient.PatientId,
		Patient.FirstName,
		Patient.LastName,
		ScheduledActivity.ScheduledStartTime,
		vv_ActivityLng.Expression1,
		ResourceActivity.ResourceSer,	
		ScheduledActivity.ScheduledActivitySer,
		CONVERT(varchar(30), Resource.ResourceSer),
		Resource.ResourceType,	
		DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight 
          FROM  
		variansystem.dbo.Patient Patient,
           	variansystem.dbo.ScheduledActivity ScheduledActivity,
		variansystem.dbo.ActivityInstance ActivityInstance,
		variansystem.dbo.Activity Activity,
		variansystem.dbo.vv_ActivityLng vv_ActivityLng,
		variansystem.dbo.ResourceActivity ResourceActivity,
		variansystem.dbo.Resource Resource
          WHERE 
            	( ScheduledActivity.ScheduledStartTime 		>= '$startOfToday' )    
            AND	( ScheduledActivity.ScheduledStartTime 		< '$endOfToday' )    
	    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
	    AND ( Patient.PatientId 				= '$PatientId') 
	    AND ( ScheduledActivity.ScheduledActivityCode 	= 'Open')
            AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
            AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
            AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
            AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
	    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'FOLLOW UP')
	    AND ( vv_ActivityLng.Expression1 			!= 'NUTRITION FIRST CONSULT')
	    AND ( vv_ActivityLng.Expression1 			!= 'FIRST CONSULT')
	    AND ( 
		  vv_ActivityLng.Expression1 			LIKE '%.EB%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%.BXC%'
		 OR vv_ActivityLng.Expression1 			LIKE '%F-U %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW UP %'
		 OR vv_ActivityLng.Expression1 			LIKE '%FOLLOW-UP%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Transfusion%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Injection%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Nursing Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Hydration%'
		 OR vv_ActivityLng.Expression1 			LIKE '%Consult%'
		 OR vv_ActivityLng.Expression1 			LIKE '%CONSULT%'
		 OR vv_ActivityLng.Expression1 			LIKE '%INTRA%'
		)
	    AND  ResourceActivity.ResourceSer = Resource.ResourceSer
	  ORDER BY ScheduledActivity.ScheduledStartTime ASC 
	";

# print the SQL query for debugging
if ($verbose) echo "Aria SQL:<br> $sqlAppt<p>";

$query = $link->query($sqlAppt);

# Loop over Aria appointments and check them in, one at a time
if ($verbose) echo "Aria loop...";
while($row = $query->fetch())
{
  #$PatientId		= $row["PatientId"];
  $PatientFirstName	= $row["FirstName"];
  $PatientLastName	= $row["LastName"];
  $ScheduledStartTime	= $row["ScheduledStartTime"];
  $ApptDescription	= $row["Expression1"];
  $ResourceSer		= $row["ResourceSer"];
  $ScheduledActivitySer	= $row["ScheduledActivitySer"];
  $AuxiliaryId		= $row["AuxiliaryId"];
  $ResourceType 	= $row["ResourceType"];
  $AptTimeSinceMidnight = $row["AptTimeSinceMidnight"];

  // Check in to Aria appointment, if there is one 
  if ($verbose) echo "<br> Aria appt: $ApptDescription at $ScheduledStartTime with $AuxiliaryId<br>";

  # since a script exists for this, best to call it here rather than rewrite the wheel
  $Aria_CheckInURL_raw = "$baseURL/php/system/checkInPatient.php?CheckinVenue=$CheckinVenue&ScheduledActivitySer=$ScheduledActivitySer";
  $Aria_CheckInURL = str_replace(' ', '%20', $Aria_CheckInURL_raw);

  if ($verbose) echo "Aria_CheckInURL: $Aria_CheckInURL<br>";

  $lines = file($Aria_CheckInURL);


} # end of Aria checkin loop

############################################################################################
######################################### Medivisit ########################################
############################################################################################
$sqlApptMedivisit = "
	  SELECT DISTINCT 
		Patient.PatientId,
		Patient.FirstName,
		Patient.LastName,
		MediVisitAppointmentList.ScheduledDateTime, 
		MediVisitAppointmentList.AppointmentCode,
		MediVisitAppointmentList.ResourceDescription,
		(UNIX_TIMESTAMP(MediVisitAppointmentList.ScheduledDateTime)-UNIX_TIMESTAMP('$startOfToday'))/60 AS AptTimeSinceMidnight, 
		MediVisitAppointmentList.AppointmentSerNum,
		MediVisitAppointmentList.Status
          FROM
		Patient,
		MediVisitAppointmentList
	 WHERE
		MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
		AND MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum 
		AND Patient.PatientId = '$PatientId'
            	AND ( MediVisitAppointmentList.ScheduledDateTime >= '$startOfToday' )    
                AND ( MediVisitAppointmentList.ScheduledDateTime < '$endOfToday' )  
		AND MediVisitAppointmentList.Status = 'Open'
		ORDER BY MediVisitAppointmentList.ScheduledDateTime
"; 

if ($verbose) echo "sqlApptMedivisit:<br> $sqlApptMedivisit<p>";

/* Process results */
$result = $conn->query($sqlApptMedivisit);

// output data of each row
while($row = $result->fetch()) 
{
  #$MV_PatientId	= $row["PatientId"];
  $MV_PatientFirstName	= $row["FirstName"];
  $MV_PatientLastName	= $row["LastName"];
  $MV_ScheduledStartTime= $row["ScheduledStartTime"];
  $MV_ApptDescription	= $row["AppointmentCode"];
  $MV_Resource		= $row["ResourceDescription"];
  $MV_AptTimeSinceMidnight= $row["AptTimeSinceMidnight"];
  $MV_AppointmentSerNum = $row["AppointmentSerNum"];
  $MV_Status		= $row["Status"];
   
  if ($verbose) echo "<br> MV appt: $MV_ApptDescription at $MV_ScheduledStartTime with $MV_Resource<br>";

  // Check in to MediVisit/MySQL appointment, if there is one 
  if ($verbose) echo "About to attempt Medivisit checkin<br>";

  # since a script exists for this, best to call it here rather than rewrite the wheel
  $MV_CheckInURL_raw = "$baseURL/php/system/checkInPatientMV.php?CheckinVenue=$CheckinVenue&ScheduledActivitySer=$MV_AppointmentSerNum";
  $MV_CheckInURL = str_replace(' ', '%20', $MV_CheckInURL_raw);

  if ($verbose) echo "MV_CheckInURL: $MV_CheckInURL<br>";

  $lines = file($MV_CheckInURL);
}

// Send patientID to James and Yick's OpalCheckin script to synchronize the checkin state with OpalDB and send notifications
if($PushNotification == 1)
{ 
	$opalCheckinURL = Config::getConfigs("opal")["OPAL_CHECKIN_URL"];

  $opalCheckinURL = "$opalCheckinURL?PatientId=$PatientId";
  $opalCheckinURL = str_replace(' ', '%20', $opalCheckinURL);

  $response = file_get_contents($opalCheckinURL);

  if(strpos($reponse, 'Error')){
	$response = array('error' => $response);
  } else {
	$response = array('success' => explode(',',  trim($response)));
  }

  $response = json_encode($response);

  print($response);
} # End of push notification
?>


