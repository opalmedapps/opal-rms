<?php
//====================================================================================
// php code to update a RAMQ expiration date in the WaitRoomManagement db
//====================================================================================

require __DIR__."/../../vendor/autoload.php";

use Orms\Config;

// Create DB connection
$dbh = Config::getDatabaseConnection("ORMS");

// Check connection
if (!$dbh) {
    die("<br>Connection failed: ");
}

#------------------------------------------------------------------------
# Extract the webpage parameters require both RAMQ and patient Id so that
# we don't accidentally update the wrong ramq
#------------------------------------------------------------------------
$PatientId = $_GET["PatientId"] ?? '';
$SSN = $_GET["Ramq"] ?? '';
$SSNExpDateMonth = $_GET["RamqExpireDateMonth"] ?? '';
$SSNExpDateYear = $_GET["RamqExpireDateYear"] ?? '';
$verbose = $_GET["verbose"] ?? '';

$Caller = $_SERVER['REMOTE_ADDR'] ?? '';

if($verbose)
{
  echo "Entering Verbose Mode..........<p/>";
  echo "Caller is: $Caller<p/>";
  echo "Got the following info: <br>";
  echo "PatientId,SSN,SSNExpDate<p>";
  echo "$PatientId,$SSN,$SSNExpDateYear$SSNExpDateMonth<p>";
}

if(!$PatientId) {exit("Error: Missing input parameter PatientId<br>");}
if(!$SSN) {exit("Error: Missing input parameter Ramq<br>");}
if(!$SSNExpDateYear) {exit("Error: Missing input parameter RamqExpireDateYear<br>");}
if(!$SSNExpDateMonth) {exit("Error: Missing input parameter RamqExpireDateMonth<br>");}

#==========================================================================================
# Find the patient using the RAMQ and PaitentId
#==========================================================================================
$PatientSerNum_sql = "SELECT PatientSerNum from Patient where Patient.PatientId = '$PatientId' and Patient.SSN = '$SSN'";

$PatientSerNum = $dbh->query($PatientSerNum_sql)->fetchColumn();

if(!$PatientSerNum)
{
    exit("<h2>ERROR: Cannot update - Patient does not exist for RAMQ $SSN and MRN $PatientId</h2><br>");
}

#==========================================================================================
# Update the db
#==========================================================================================
$update_patient_sql = "
    UPDATE Patient
    SET
        SSNExpDate = '$SSNExpDateYear$SSNExpDateMonth',
        LastUpdatedUserIP = '$Caller'
    WHERE
        Patient.PatientSerNum = $PatientSerNum
";
if($verbose ){echo "update_patient_sql: $update_patient_sql<br>";}
if($dbh->query($update_patient_sql))
{
    echo "<h2>RAMQ expiration date has been updated to $SSNExpDateYear$SSNExpDateMonth for patient with RAMQ $SSN and MRN $PatientId</h2><br>";
}
else
{
    exit("Error: Unable to update patient record for Patient with RAMQ $SSN and MRN $PatientId - Errno: ". print_r($dbh->errorInfo()) ."<br>");
}

?>
