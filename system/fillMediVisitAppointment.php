<?php
//==================================================================================== 
// fillMediVisitAppointment.php - php code to insert a Medivisit patient into the
// ORMS Waiting Room Management system database on MySQL
//==================================================================================== 

# Example:
# `LOCATION` /fillMediVisitAppointment.php?PatientId=12345&Ramq=ABCDEF123456&RamqExpireDate=0516&PatLastName=XXX12&PatFirstName=John&ResourceCode=NSBLD&ResourceName=NS%20-%20prise%20de%20sang/blood%20tests%20pre/post%20tx&AppointDate=01/06/2017&AppointTime=10:50&AppointCode=BLD-XY&Status=Open&Action=DEL&AppointId=2019081337&AppointSys=Medivisit&verbose=0

$baseLoc = dirname(__FILE__);
require("$baseLoc/../php/config_screens.php");

// Variables to be used throughout
$PatientSerNun;

$database = WAITROOM_DB; # whether Dev or prod specified in config, can override here if needed 

// Create DB connection
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, $database);

// Check connection
if ($conn->connect_error) {
    die("<br>Connection failed: " . $conn->connect_error);
} 

#------------------------------------------------------------------------
# Extract the webpage parameters
#------------------------------------------------------------------------
$PatientId		= $_POST["PatientId"];
$LastName		= $_POST["PatLastName"];
$FirstName		= $_POST["PatFirstName"];
$SSN			= $_POST["Ramq"];
$SSNExpDate		= $_POST["RamqExpireDate"];
$Resource		= $_POST["ResourceCode"];
$ResourceDesc		= $_POST["ResourceName"];
$ScheduledDate 		= $_POST["AppointDate"];
$ScheduledTime  	= $_POST["AppointTime"];
$AppointmentCode	= $_POST["AppointCode"];
$Status 		= $_POST["Status"];
$Action			= $_POST["Action"];
$AppointId 		= $_POST["AppointId"];	
$AppointSys		= $_POST["AppointSys"];	
$verbose		= $_POST["verbose"];

# Remove the 2017A or equivalent from the start of the appointment ID to get the real appointment ID.
# However, keep the original provided by the Interface Engine just in case something doesn't match
$AppointIdIn 		= $AppointId;
$AppointId		= substr($AppointId, 5); # remove the 2017A from the appointment as the A changes to C when cancelled

# Check the status - true appointments come in as A, cancels come in as C
$AppointStatusLetter    = substr($AppointIdIn, 4, 1); # the status letter comes after the creation year
if($AppointStatusLetter == "C")
{
  $Status = "Deleted";
}


$Caller 		= $_SERVER['REMOTE_ADDR'];

if($verbose) 
{
  echo "Entering Verbose Mode..........<p/>";
  echo "Caller is: $Caller<p/>";
  echo "Using database: $database<br>";
  echo "Status letter: $AppointStatusLetter<br>";
  echo "Got the following info: <br>";
  echo "PatientId,LastName,FirstName,SSN,SSNExpDate,Resource,ResourceDesc,ScheduledDate,ScheduledTime,AppointmentCode,Status,Action,AppointId,AppointIdIn,AppointSys<p>";
  echo "$PatientId,$LastName,$FirstName,$SSN,$SSNExpDate,$Resource,$ResourceDesc,$ScheduledDate,$ScheduledTime,$AppointmentCode,$Status,$Action,$AppointId,$AppointIdIn,$AppointSys<p>";
}

if(!$LastName)		{exit("Error: Missing input parameter PatLastName<br>");}
if(!$FirstName)		{exit("Error: Missing input parameter PatFirstName<br>");}
if(!$Resource)		{exit("Error: Missing input parameter ResourceCode<br>");}
if(!$ResourceDesc)	{exit("Error: Missing input parameter ResourceName<br>");}
if(!$ScheduledDate)	{exit("Error: Missing input parameter AppointDate<br>");}
if(!$ScheduledTime)	{exit("Error: Missing input parameter AppointTime<br>");}
if(!$AppointmentCode)	{exit("Error: Missing input parameter AppointCode<br>");}
if(!$Status)		{exit("Error: Missing input parameter Status<br>");}
if(!$Action)		{exit("Error: Missing input parameter Action<br>");}
if(!$AppointId)		{exit("Error: Missing input parameter AppointId<br>");}
if(!$AppointSys)	{exit("Error: Missing input parameter AppointSys<br>");}

# We must have either the RAMQ or the MRN but we allow one to be empty
if(!$PatientId && !$SSN) {exit("Error: Need at least one of MRN or RAMQ <br>");}

# If the RAMQ is provided, then its exp date must also be provided
if($SSN && !$SSNExpDate) {exit("Error: Missing parameter RamqExpireDate (must be provided when Ramq is provided)<br>");}

# Only Medivisit appointments allowed right now so check if AppointSys = Medivisit, if not exit with error
if($AppointSys != "Medivisit")
{
	exit("Error: Unknown appointment system $AppointSys (only Medivist accepted) <br>");
}

#==========================================================================================
# Logic: 3 actions possible:
# 1) ADD [S12] - add new appointment and possibly add new patient
# 2) UPD [S14] - update appointment and possibly update patient
# 3) DEL [S17] - delete appointment (treat as an update except with Status = "Delete") and possibly update patient
#==========================================================================================

#==========================================================================================
# Parse the date for the given appointment - will use when inserting the 
# appointment 
#==========================================================================================
$ScheduledDate_parsed 	= date_parse($ScheduledDate);

$month_Date 		= $ScheduledDate_parsed["month"];
$day_Date 		= $ScheduledDate_parsed["day"];
$year_Date 		= $ScheduledDate_parsed["year"];
$date_for_dow 		= "$year_Date-$month_Date-$day_Date";
$dow 			= date("D",strtotime($ScheduledDate));

if($day_Date < 10)	{$day_Date = "0$day_Date";}
if($month_Date < 10)	{$month_Date = "0$month_Date";}

$ScheduledDateTime 	= "$year_Date-$month_Date-$day_Date $ScheduledTime:00";
$ScheduledDate 		= "$year_Date-$month_Date-$day_Date";
$ScheduledTime 		= "$ScheduledTime:00";
$hour 			= date("h",strtotime($ScheduledTime)); 

$AMorPM;
if($hour < 13)
{
$AMorPM = "AM";
}
else
{
$AMorPM = "PM";
}

#==========================================================================================
# As a sanity check, look to see if the Medivisit resource exists in
# MySQL. If it does not then the kiosk will not be able to tell the patient
# where to go. While we will allow the patient's appointment to be entered
# we should make note of the missing clinic so that it can be fixed 
#==========================================================================================
$MediVisitResource_sql = "SELECT ClinicResourcesSerNum from ClinicResources where ResourceName = '$ResourceDesc'";

if($verbose){echo "MediVisitResource_sql: $MediVisitResource_sql<br>";}

$result = $conn->query($MediVisitResource_sql);

if($result->num_rows > 0)
{
	$array = $result->fetch_assoc();
	$ClinicResourcesSerNum = $array['ClinicResourcesSerNum'];
	if($verbose){echo "Got a ClinicResourcesSerNum: " . $ClinicResourcesSerNum. "<br>";}
}
else
{
	$sql_insert_hangingclinic = "INSERT INTO HangingClinics(MediVisitName,MediVisitResourceDes) VALUES ('$Resource','$ResourceDesc')";

	if($conn->query($sql_insert_hangingclinic))
	{
		// inserted fine, nothing to worry about
	}
	else
	{
		exit("Error: Unable to insert into HangingClinics - Errno: $conn->errno $conn->error . \"<br>\"");
	}
}


#==========================================================================================
# Start of: Action = UPD or DEL
#==========================================================================================
if($Action == "UPD" OR $Action == "DEL")
{
  # If the action is UPD or DEL then we will update an existing appointment in the database using
  # the AppointId as the appointment identifier. The appointment (and thus the patient) should exist
  # in our database already but it is possible that the appointment exists on Medivisit already but
  # didn't make it across to our database. If this is the case we will ADD the appointment as though
  # we were adding it from scratch
 
  # We don't allow delete - instead we just update the existing entry in the database to have a
  # status of Deleted 

  if($verbose && $Action == "UPD"){echo "Action is UPD => Will modify database using AppointId=\"$AppointId\" (AppointSys=$AppointSys) as key<p>";}
  if($verbose && $Action == "DEL"){echo "Action is DEL => Will modify database and set Status of AppointId=\"$AppointId\" (AppointSys=$AppointSys) to Deleted<p>";}

  #------------------------------------------------------------------------
  # Get the PatientSerNum from the existing appointment if it exists 
  #------------------------------------------------------------------------
  $AppointId_sql = "SELECT PatientSerNum from MediVisitAppointmentList where AppointId = \"$AppointId\"";

  $apt_result = $conn->query($AppointId_sql);

  # Appointment does not exist, we will need to create it as though we were adding a new appointment
  # from scratch - the easiest thing to do is to recall the whole script
  if($apt_result->num_rows == 0)
  {
    if($verbose){echo "Appointment does not exist... will check for patient<br>";}
    
    # set the Action as ADD so that we will add the appointment below 
    $Action = "ADD";
  }
  else # deal with the update now
  {
    $array = $apt_result->fetch_assoc();
    $PatientSerNum = $array['PatientSerNum'];
    if($verbose){echo "Got patient for update with SerNum: " . $PatientSerNum . "<br>";}

    if($Action == "UPD")
    {
      	$update_sql = "
		UPDATE MediVisitAppointmentList 
		SET 
			Resource		= \"$Resource\",
			ResourceDescription	= \"$ResourceDesc\",
			ClinicResourcesSerNum	=  $ClinicResourcesSerNum,
			ScheduledDateTime	= \"$ScheduledDateTime\",
			ScheduledDate		= \"$ScheduledDate\",
			ScheduledTime		= \"$ScheduledTime\",
			AppointmentCode		= \"$AppointmentCode\",
			Status			= \"$Status\",
			LastUpdatedUserIP       = \"$Caller\",
			AppointIdIn		= \"$AppointIdIn\"
		WHERE 
			AppointId 		= \"$AppointId\"
			AND AppointSys 		= \"$AppointSys\"
		  ";
    }
    elseif($Action == "DEL")
    {
    	$update_sql = "
		UPDATE MediVisitAppointmentList 
		SET 
			Resource		= \"$Resource\",
			ResourceDescription	= \"$ResourceDesc\",
			ClinicResourcesSerNum	=  $ClinicResourcesSerNum,
			ScheduledDateTime	= \"$ScheduledDateTime\",
			ScheduledDate		= \"$ScheduledDate\",
			ScheduledTime		= \"$ScheduledTime\",
			AppointmentCode		= \"$AppointmentCode\",
			Status			= \"Deleted\",
			LastUpdatedUserIP       = \"$Caller\",
			AppointIdIn		= \"$AppointIdIn\"
		WHERE 
			AppointId 		= \"$AppointId\"
			AND AppointSys 		= \"$AppointSys\"
		  ";
    }
    else
    {
	exit("Error: Unkown Action $Action <br>");
    }
  
    if($verbose ){echo "update_sql: $update_sql<br>";}

    # Update the appointment
    if($conn->query($update_sql))
    {
      // updated fine, nothing to worry about
      if($verbose ){echo "Appointment update successful (SSN: $SSN, PatientId: $PatientId)<br>";}

      // Update the patient - UPD signals can be to update the patient, not just the appointment
      // For example, part of the patient's information may be missing at the time of appointment
      // creation and then is filled in later

      // Only allow an update on the Patient table if either the Ramq or MRN (or both) are provided. At the time 
      // of an update, both of these should be available in the ADT
      if($SSN || $PatientId)
      {
        if($verbose ){echo "updating patient info...<br>";}
	if(($SSN && $PatientId)) # both
	{
          if($verbose){echo "Updating with both SSN and PatientId<br>";}
	  $update_patient_sql = "
			UPDATE Patient
			SET
				LastName 	= \"$LastName\",
				FirstName 	= \"$FirstName\",
				SSN 		= \"$SSN\",
				SSNExpDate 	= \"$SSNExpDate\",
				PatientId	= \"$PatientId\"
			WHERE
				Patient.PatientSerNum = $PatientSerNum 
		    ";	
	}
        elseif($SSN)
  	{
          if($verbose){echo "Updating with SSN alone<br>";}
	  $update_patient_sql = "
			UPDATE Patient
			SET
				LastName 	= \"$LastName\",
				FirstName 	= \"$FirstName\",
				SSN 		= \"$SSN\",
				SSNExpDate 	= \"$SSNExpDate\"
			WHERE
				Patient.PatientSerNum = $PatientSerNum 
		    ";	
	}
	elseif($PatientId)
	{
          if($verbose){echo "Updating with PatientId alone<br>";}
	  $update_patient_sql = "
			UPDATE Patient
			SET
				LastName 	= \"$LastName\",
				FirstName 	= \"$FirstName\",
				PatientId	= \"$PatientId\"
			WHERE
				Patient.PatientSerNum = $PatientSerNum 
		    ";	
	}	
	# Do the actual update of the patient
       	if($verbose ){echo "update_patient_sql: $update_patient_sql<br>";}
        if($conn->query($update_patient_sql))
        {
    	  // inserted fine, nothing to worry about
        }
        else
        {
      	  exit("Error: Unable to update patient record for Patient PatintSerNum: $PatintSerNum 
		- Errno: $conn->errno $conn->error <br>");
        }
      }
    }
    else
    {
	exit("<p>Error: Unable to update record for AppointId=\"$AppointId\" (AppointSys=$AppointSys)- Errno: $conn->errno $conn->error . \"<br>\"");
    }
  }
}
#==========================================================================================
# End of: Action = UPD or DEL
#==========================================================================================

#==========================================================================================
# Start of: Action = ADD
#==========================================================================================
if($Action == "ADD")
{
  # First check that for some reason the appointment doesn't already exist
  $AppointId_sql = "SELECT PatientSerNum from MediVisitAppointmentList where AppointId = \"$AppointId\"";

  $apt_result = $conn->query($AppointId_sql);

  # Appointment does not exist, we will need to create it as though we were adding a new appointment
  # from scratch - the easiest thing to do is to recall the whole script
  if($apt_result->num_rows == 0)
  {
    if($verbose){echo "Appointment does not exist... will go ahead and add<br>";}
  }
  else
  {
	exit("<p>Error: Unable to add record for AppointId=\"$AppointId\" (AppointSys=$AppointSys) as this AppointId already exists<br>");
  }

  # If the action is ADD, then we are adding a new appointment that doesn't already exist and
  # possibly a new patient that doesn't already exist
  if($verbose){echo "Action is ADD => Will add new data to database<p>";}

  #==========================================================================================
  # See if the patient exists in the MySQL Patient table and if so, get the 
  # PatienSerNum to be used later - use either the MRN or the RAMQ to search for the patient 
  #==========================================================================================
  if( isset($SSN) )
  {
    if($verbose){echo "Got an SSN , will use it to find PatientSerNum <br>";}
    $Patient_sql = "SELECT PatientSerNum from Patient where SSN = '$SSN'";
  }
  elseif( isset($PatientId) )
  {
    if($verbose){echo "Got a PatientId, will use it to find PatientSerNum <br>";}
    $Patient_sql = "SELECT PatientSerNum from Patient where PatientId = '$PatientId'";
  }
  else
  {
    exit("Error: Need either PatientId or Ramq to identify the patient <br>");
  }

  if($verbose){echo "Patient_sql: $Patient_sql<br>";}

  $result = $conn->query($Patient_sql);

  if($result->num_rows > 0)
  {
    $array = $result->fetch_assoc();
    $PatientSerNum = $array['PatientSerNum'];
    if($verbose){echo "Got patient with SerNum: " . $PatientSerNum . "<br>";}
  }

  #==========================================================================================
  # if patient doesn't exist, insert new, using all the information given 
  #==========================================================================================
  if( !isset($PatientSerNum) )
  {
    if($verbose){echo "No PatientSerNum... going to create one <br>";}

    // prepare query
    $sql_insert_patient = "INSERT INTO Patient(LastName,FirstName,SSN,SSNExpDate,PatientId,LastUpdatedUserIP) VALUES ('$LastName','$FirstName','$SSN','$SSNExpDate','$PatientId','$Caller')";

    if($verbose){echo "sql_insert_patient $sql_insert_patient<br>";}

    // run query
    if($conn->query($sql_insert_patient))
    {
      // get the patient's new SerNum
      $result = $conn->query($Patient_sql);

      if($result->num_rows > 0)
      {
	$array = $result->fetch_assoc();
	$PatientSerNum = $array['PatientSerNum'];
	if($verbose){echo "Created new patient with SerNum: " . $PatientSerNum . "<br>";}
      }
    }
    else
    {
	exit("Error: Unable to create new patient - Errno: $conn->errno $conn->error <br>");
    }
  } # end of new patient insert

  #------------------------------------------------------------------------
  # Insert Medivisit Appointment as given
  #------------------------------------------------------------------------
  # Just to be sure, set the status as Open if it has not already been set as Deleted
  if($Status != "Deleted")
  {
    $Status = "Open";
  }

  $sql_insert_appointment = "INSERT INTO MediVisitAppointmentList (PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,AppointId,AppointIdIn,AppointSys,Status,LastUpdatedUserIP) VALUES ('$PatientSerNum','$Resource','$ResourceDesc','$ClinicResourcesSerNum','$ScheduledDateTime','$ScheduledDate','$ScheduledTime','$AppointmentCode','$AppointId','$AppointIdIn','$AppointSys','$Status','$Caller')";
  if($conn->query($sql_insert_appointment))
  {
    // inserted fine, nothing to worry about
  }
  else
  {
      exit("Error: Unable to insert into MediVisitAppointmentList - Errno: $conn->errno $conn->error <br>");
  }
}
#==========================================================================================
# End of: Action = ADD
#==========================================================================================


# If we get to here then just return "success"
echo "success";

# Close the database connection before exiting
$conn->close();
?>


