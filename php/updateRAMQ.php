<?php
//==================================================================================== 
// updateRAMQ.php - php code to update a RAMQ expiration date in the WaitRoomManagement db 
//==================================================================================== 

include_once("config_screens.php");

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
# Extract the webpage parameters require both RAMQ and patient Id so that
# we don't accidentally update the wrong ramq
#------------------------------------------------------------------------
$PatientId		= $_GET["PatientId"];
$SSN			= $_GET["Ramq"];
$SSNExpDateMonth	= $_GET["RamqExpireDateMonth"];
$SSNExpDateYear		= $_GET["RamqExpireDateYear"];
$verbose		= $_GET["verbose"];

$Caller 		= $_SERVER['REMOTE_ADDR'];

if($verbose) 
{
  echo "Entering Verbose Mode..........<p/>";
  echo "Caller is: $Caller<p/>";
  echo "Using database: $database<br>";
  echo "Got the following info: <br>";
  echo "PatientId,SSN,SSNExpDate<p>";
  echo "$PatientId,$SSN,$SSNExpDateYear$SSNExpDateMonth<p>";
}

if(!$PatientId)		{exit("Error: Missing input parameter PatientId<br>");}
if(!$SSN)		{exit("Error: Missing input parameter Ramq<br>");}
if(!$SSNExpDateYear)	{exit("Error: Missing input parameter RamqExpireDateYear<br>");}
if(!$SSNExpDateMonth)	{exit("Error: Missing input parameter RamqExpireDateMonth<br>");}

#==========================================================================================
# Find the patient using the RAMQ and PaitentId
#==========================================================================================
$PatientSerNum_sql = "SELECT PatientSerNum from Patient where Patient.PatientId = \"$PatientId\" and Patient.SSN = \"$SSN\"";

$pat_result = $conn->query($PatientSerNum_sql);

if($pat_result->num_rows == 0)
{
    exit("<h2>ERROR: Cannot update - Patient does not exist for RAMQ $SSN and MRN $PatientId</h2><br>");
    
    # set the Action as ADD so that we will add the appointment below 
    $Action = "ADD";
}
else # deal with the update now
{
  $array = $pat_result->fetch_assoc();
  $PatientSerNum = $array['PatientSerNum'];
}

#==========================================================================================
# Update the db
#==========================================================================================
$update_patient_sql = "
			UPDATE Patient
			SET
				SSNExpDate 	= \"$SSNExpDateYear$SSNExpDateMonth\",
				LastUpdatedUserIP = \"$Caller\"
			WHERE
				Patient.PatientSerNum = $PatientSerNum
";	
if($verbose ){echo "update_patient_sql: $update_patient_sql<br>";}
if($conn->query($update_patient_sql))
{
    	echo "<h2>RAMQ expiration date has been updated to $SSNExpDateYear$SSNExpDateMonth for patient with RAMQ $SSN and MRN $PatientId</h2><br>";
}
else
{
     	exit("Error: Unable to update patient record for Patient with RAMQ $SSN and MRN $PatientId
		- Errno: $conn->errno $conn->error <br>");
}

# Close the database connection before exiting
$conn->close();
?>


