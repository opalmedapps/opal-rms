#!/opt/perl5/perl
#---------------------------------------------------------------------------------------------------------------
# Script that inserts or updates an appointment in the ORMS database.
# Can be called using command line arguments or POST
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use warnings;
use v5.30;
use lib "./modules";

use LoadConfigs;
use Time::Piece;
use Getopt::Long qw(:config no_auto_abbrev pass_through);

#connect to the database
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or returnMessageAndExit("Couldn't connect to database");

#-----------------------------------------
#get appointment information from script parameters
#-----------------------------------------

#appointment parameters
my $appointment = {
	action=> undef,
	id=> undef,
	code=> undef,
	creationDate=> undef,
	firstName=> undef,
	lastName=> undef,
	patientId=> undef,
	referringMd=> undef,
	resource=> undef,
	resourceDesc=> undef,
	scheduledDate=> undef,
	scheduledTime=> undef,
	site=> undef,
	ssn=> undef,
	ssnExpDate=> undef,
	status=> undef,
	system=> undef,
	type=> undef,

	caller=> undef
};

my $args = {};
for(keys %{$appointment})
{
	GetOptions("$_:s",\$appointment->{$_});

	#if some information is missing, exit
	returnMessageAndExit("Incomplete appointment information") if(!defined $appointment->{$_});
}

#if the appointment ID is 'InstantAddOn', then the appointment does not exist in Medivist
#we need to generate an appointment ID
$appointment->{'id'} = "$appointment->{'ssn'}-$appointment->{'scheduledDate'}-$appointment->{'scheduledTime'}" if($appointment->{'id'} eq "InstantAddOn");

#get the clinic serial number
my $sqlClinic = "
	SELECT
		ClinicResources.ClinicResourcesSerNum
	FROM
		ClinicResources
	WHERE
		ClinicResources.ResourceName = ?";

my $queryClinic = $dbh->prepare_cached($sqlClinic) or returnMessageAndExit("Couldn't prepare statement: ". $dbh->errstr);
$queryClinic->execute($appointment->{'resourceDesc'}) or returnMessageAndExit("Couldn't execute statement: ". $queryClinic->errstr);

my $clinicSer = $queryClinic->fetchall_arrayref({})->[0]->{'ClinicResourcesSerNum'} || "";

#if the clinic doesn't exist, insert it in the db
if(!$clinicSer)
{
	my $speciality = $appointment->{'site'};
	$speciality = "Oncology" if($speciality eq "RVH");
	$speciality = "Ortho" if($speciality eq "MGH");

	my $insertClinicResult = $dbh->do("
		INSERT INTO ClinicResources (ResourceName,Speciality,ClinicScheduleSerNum)
		SELECT 
			?,
			?,
			ClinicSchedule.ClinicScheduleSerNum
		FROM ClinicSchedule
		WHERE
			ClinicSchedule.ClinicName LIKE ?
		",undef,
		$appointment->{'resourceDesc'},
		$speciality,
		"%$speciality Default%"
	);

	returnMessageAndExit("Clinic insert failed") if(!defined $insertClinicResult or $insertClinicResult eq '0E0');


	#my $insertClinicResult = $dbh->do("INSERT INTO HangingClinics(MediVisitName,MediVisitResourceDes) VALUES (?,?)",undef,$appointment->{'resource'},$appointment->{'resourceDesc'});

	#if the unknown clinic is already in HangingClinics, the insert statement will always fail, returning undef
	#better to simply pass unknown clinic message
	#returnMessageAndExit("Could not insert $appointment->{'resource'} $appointment->{'resourceDesc'}' into the Hanging Clinic table") if(!defined $insertClinicResult or $insertClinicResult eq '0E0');

	#returnMessageAndExit("Hanging Clinic (Unknown Clinic)");
}

#find the patient for who the appointment is for
#if one doesn't exist, create a new patient
my $sqlPatientExists = "
	SELECT
		Patient.PatientSerNum,
		Patient.PatientId,
		Patient.SSN,
		Patient.SSNExpDate
	FROM
		Patient
	WHERE
		Patient.SSN = ?
		AND (Patient.PatientId = ? OR Patient.PatientId_MGH = ?)";

my $queryPatientExists = $dbh->prepare_cached($sqlPatientExists) or returnMessageAndExit("Couldn't prepare statement: ". $dbh->errstr);
$queryPatientExists->execute($appointment->{'ssn'},$appointment->{'patientId'},$appointment->{'patientId'}) or returnMessageAndExit("Couldn't execute statement: ". $queryPatientExists->errstr);

my $existingPatientResult = $queryPatientExists->fetchall_arrayref({});
my $existingPatient = {
	patientSer=> $existingPatientResult->[0]->{'PatientSerNum'} || "",
	patientId=> $existingPatientResult->[0]->{'PatientId'} || "",
	ssn=> $existingPatientResult->[0]->{'SSN'} || "",
	ssnExpDate=> $existingPatientResult->[0]->{'SSNExpDate'} || ""
};

#see if the appointment already exists
my $sqlAppointmentExists = "
	SELECT
		MediVisitAppointmentList.AppointmentSerNum,
		MediVisitAppointmentList.Status
	FROM
		MediVisitAppointmentList
	WHERE
		MediVisitAppointmentList.AppointId = ?";

my $queryAppointmentExists = $dbh->prepare_cached($sqlAppointmentExists) or returnMessageAndExit("Couldn't prepare statement: ". $dbh->errstr);
$queryAppointmentExists->execute($appointment->{'id'}) or returnMessageAndExit("Couldn't execute statement: ". $queryAppointmentExists->errstr);

my $existingAppointmentResult = $queryAppointmentExists->fetchall_arrayref({});
my $existingAppointmentSer = $existingAppointmentResult->[0]->{'AppointmentSerNum'} || "";
my $existingAppointmentStatus = $existingAppointmentResult->[0]->{'Status'} || "";

#----------------------------------------------------------
#insert/update the appointment in the database
#----------------------------------------------------------
#3 possibilities:
# ADD [S12] - add new appointment and possibly add new patient
# UPD [S14] - update appointment
# DEL [S17] - delete appointment (treat as an update except with Status = "Delete")

if($appointment->{'action'} eq "ADD")
{
	insertNewAppointment($appointment,$existingAppointmentSer,$existingPatient);
}
elsif($appointment->{'action'} eq "UPD")
{
	###################################################################
	# The logic in this section is currently for appointments coming in from MediVisit.
	# In theory, updates coming in are supposed to be for existsing appointments, but in the case when an appointment date/time is changed,
	#  the appointment is deleted in MediVisit and a new appointment is created. What happends is that we get two calls in our system,
	#  a DEL and an UPD with different appointment IDs. This UPD should be treated as a new appointment.
	###################################################################

	#if the appointment doesn't exist, create a new one
	#otherwise, update it with the new information
	insertNewAppointment($appointment,$existingAppointmentSer,$existingPatient) unless($existingAppointmentSer);

	#appointment completions are not sent to us so if the appointment is completed, do not modify it
	returnMessageAndExit("Appointment already completed") if($existingAppointmentStatus eq 'Completed');

	my $updateAppointmentResult = $dbh->do("
		UPDATE MediVisitAppointmentList
		SET
			Resource = ?,
			ResourceDescription = ?,
			ClinicResourcesSerNum = ?,
			ScheduledDateTime = ?,
			ScheduledDate = ?,
			ScheduledTime = ?,
			AppointmentCode = ?,
			AppointSys = ?,
			Status = ?,
			CreationDate = ?,
			ReferringPhysician = ?,
			LastUpdatedUserIP = ?
		WHERE
			AppointId = ?",undef,
		$appointment->{'resource'},
		$appointment->{'resourceDesc'},
		$clinicSer,
		"$appointment->{'scheduledDate'} $appointment->{'scheduledTime'}",
		$appointment->{'scheduledDate'},
		$appointment->{'scheduledTime'},
		$appointment->{'code'},
		$appointment->{'system'},
		$appointment->{'status'},
		$appointment->{'creationDate'},
		$appointment->{'referringMd'},
		$appointment->{'caller'},
		#WHERE
		$appointment->{'id'});

	returnMessageAndExit("Appointment update failed") if(!defined $updateAppointmentResult or $updateAppointmentResult eq '0E0');

	#if the patient's ssn or ssn expiry date has changed, it should be updated now
	updatePatientSSN($existingPatient,$appointment) if($existingPatient->{'ssn'} ne $appointment->{'ssn'} or $existingPatient->{'ssnExpDate'} ne $appointment->{'ssnExpDate'});

	returnMessageAndExit("success");

}
elsif($appointment->{'action'} eq "DEL")
{
	$appointment->{'status'} = 'Deleted' if($appointment->{'action'} eq 'DEL');

	#if the appointment doesn't exist, exit
	returnMessageAndExit("Can't delete - Appointment does not exist") unless($existingAppointmentSer);

	my $deleteAppointmentResult = $dbh->do("
		UPDATE MediVisitAppointmentList
		SET
			Status = 'Deleted',
			LastUpdatedUserIP = ?
		WHERE
			AppointmentSerNum = ?
			AND AppointId = ?",undef,$appointment->{'caller'},$existingAppointmentSer,$appointment->{'id'});

	returnMessageAndExit("Appointment delete failed") if(!defined $deleteAppointmentResult or $deleteAppointmentResult eq '0E0');

	returnMessageAndExit("success");
}

#creates a new appointment in the ORMS database
sub insertNewAppointment
{
	my $appointment = shift;
	my $existingAppointmentSer = shift;
	my $existingPatient = shift;

	#if the appointment already exists, exit
	returnMessageAndExit("Appointment already exists") if($existingAppointmentSer);

	#create patient if the patient is not in the database -> catch the error if ramq or id already matches
	if(!$existingPatient->{'patientSer'})
	{
		my $insertPatientStatus = $dbh->do("INSERT INTO Patient(FirstName,LastName,SSN,SSNExpDate,PatientId) VALUES (?,?,?,?,?)",undef,$appointment->{'firstName'},$appointment->{'lastName'},$appointment->{'ssn'},$appointment->{'ssnExpDate'},$appointment->{'patientId'});

		returnMessageAndExit("Could not create new patient in db") if(!defined $insertPatientStatus or $insertPatientStatus eq '0E0');

		$existingPatient->{'patientSer'} = $dbh->last_insert_id(undef,undef,'Patient','PatientSerNum') or returnMessageAndExit("Could not get new patient serial number");
	}
	else
	{
		#if the patient's ssn or ssn expiry date has changed, it should be updated now
		updatePatientSSN($existingPatient,$appointment) if($existingPatient->{'ssn'} ne $appointment->{'ssn'} or $existingPatient->{'ssnExpDate'} ne $appointment->{'ssnExpDate'});
	}

	#insert the new appointment
	my $insertAppointmentResult = $dbh->do("
		INSERT INTO MediVisitAppointmentList(
			PatientSerNum,
			Resource,
			ResourceDescription,
			ClinicResourcesSerNum,
			ScheduledDateTime,
			ScheduledDate,
			ScheduledTime,
			AppointmentCode,
			AppointId,
			AppointIdIn,
			AppointSys,
			Status,
			CreationDate,
			ReferringPhysician,
			LastUpdatedUserIP
		)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",undef,
		$existingPatient->{'patientSer'},
		$appointment->{'resource'},
		$appointment->{'resourceDesc'},
		$clinicSer,
		"$appointment->{'scheduledDate'} $appointment->{'scheduledTime'}",
		$appointment->{'scheduledDate'},
		$appointment->{'scheduledTime'},
		$appointment->{'code'},
		$appointment->{'id'},
		$appointment->{'id'},
		$appointment->{'system'},
		$appointment->{'status'},
		$appointment->{'creationDate'},
		$appointment->{'referringMd'},
		$appointment->{'caller'}
	);

	returnMessageAndExit("Appointment insert failed (it may already exist)") if(!defined $insertAppointmentResult or $insertAppointmentResult eq '0E0');

	returnMessageAndExit("success");
}

#updates patient ssn information
sub updatePatientSSN
{
	my $existingPatient = shift;
	my $appointment = shift;

	my $updatePatientResult = $dbh->do("
		UPDATE Patient
		SET
			SSN = ?,
			SSNExpDate = ?
		WHERE
			SSN = ?
			AND (PatientId = ? OR PatientId_MGH = ?)",undef,
		$appointment->{'ssn'},
		$appointment->{'ssnExpDate'},
		#WHERE
		$existingPatient->{'ssn'},
		$existingPatient->{'patientId'},
		$existingPatient->{'patientId'}
	);

	returnMessageAndExit("Patient update failed") if(!defined $updatePatientResult or $updatePatientResult eq '0E0');
}

#prints a message (usually to another script that called this one) and exits
sub returnMessageAndExit
{
	my $message = shift;
	print $message;

	exit;
}


