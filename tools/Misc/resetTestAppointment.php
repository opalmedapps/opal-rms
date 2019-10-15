<?php
//==================================================================================== 
// php script to create/reset the test patient's test appointment
//====================================================================================
$fileLocation = dirname(__FILE__);
include_once("$fileLocation/../../php/class/Config.php");

$dbh = Config::getDatabaseConnection("ORMS");

if (!$dbh) {
    die('Something went wrong while connecting to MySQL');
}

#for the test appointment, remove all PatientLocation and PatientLocationMH entries

$host = gethostname();

foreach(['TEST1','TEST2'] as $appId)
{
	$dbh->exec("
		DELETE FROM PatientLocation 
		WHERE PatientLocation.AppointmentSerNum = 
			(SELECT MV.AppointmentSerNum FROM MediVisitAppointmentList MV WHERE MV.AppointId = '$appId')
	");

	$dbh->exec("
		DELETE FROM PatientLocationMH 
		WHERE PatientLocationMH.AppointmentSerNum = 
			(SELECT MV.AppointmentSerNum FROM MediVisitAppointmentList MV WHERE MV.AppointId = '$appId')");

	$dbh->exec("
		INSERT INTO MediVisitAppointmentList
		(PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,AppointId,AppointIdIn,AppointSys,Status,CreationDate,LastUpdatedUserIP)
		VALUES
		(827,'TX2-V','Oncologie Traitement - Glen',3213,CONCAT(CURDATE(),' 23:30'),CURDATE(),'23:30','CHM-SHORT','$appId','$appId','InstantAddOn','Open',CURDATE(),'$host')
		ON DUPLICATE KEY UPDATE
		PatientSerNum = VALUES(PatientSerNum),
		Resource = VALUES(Resource),
		ResourceDescription = VALUES (ResourceDescription),
		ClinicResourcesSerNum = VALUES(ClinicResourcesSerNum),
		ScheduledDateTime = VALUES(ScheduledDateTime),
		ScheduledDate = VALUES(ScheduledDate),
		ScheduledTime = VALUES(ScheduledTime),
		AppointmentCode = VALUES(AppointmentCode),
		AppointId = VALUES(AppointId),
		AppointIdIn = VALUES(AppointIdIn),
		AppointSys = VALUES(AppointSys),
		Status = VALUES(Status),
		CreationDate = VALUES(CreationDate),
		LastUpdatedUserIP = VALUES(LastUpdatedUserIP)");
}

?>
