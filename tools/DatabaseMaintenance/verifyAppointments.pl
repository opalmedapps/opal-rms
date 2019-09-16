#!/opt/perl5/perl

#-----------------------------------------------
# Script that updates the orms db with the proper first and last names from the hospital ADT
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
#use warnings;
use v5.30;

use Cwd qw(abs_path);

#my $runningDir;
#BEGIN {
#	$runningDir = abs_path(__FILE__ ."/../");
#}

#use lib "$runningDir/../../perl/system/modules/";
#use LoadConfigs;
use HTTP::Request::Common;
use LWP::UserAgent;
use XML::Hash::XS;
use File::JSON::Slurper qw(read_json write_json);
use Text::CSV qw(csv);

use Data::Dumper;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Hash::XS->new();

my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request

#read the adt data object
my $adtData = read_json('adtData.json');
my $ormsToOacis = read_json('ormsToOacis.json');
my $oacisIdToOrms = read_json('oacisIdToOrms.json');
my @unknowns = read_json('unknown.json')->@*;
my $visitData = read_json('visits.json');
my $appointmentData = read_json('appointments.json');
my $encounterData = read_json('encounters.json');

# my $adtData;
# my $ormsToOacis;
# my $oacisIdToOrms;
# my @unknowns;
# my $visitData;
# my $appointmentData;
# my $encounterData;
# createAndSaveHashes();
# exit;

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
#my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#custom connection
use DBI;
my $dbh = DBI->connect_cached("DBI:MariaDB:database=WaitRoomManagement;host=172.26.66.41",'readonly','readonly') or die("Can't connect");

#exclude all patients which we couldn't find in the ADT
my $excludeString = join(",",@unknowns);
$excludeString = "AND MV.PatientSerNum NOT IN ($excludeString)" if($excludeString);

#get a list of all the patients in the database
my $sqlPatientList = "
	SELECT 
		MV.PatientSerNum,
		MV.ScheduledDateTime,
		MV.ScheduledDate,
		MV.ScheduledTime,
		MV.AppointId,
		MV.AppointIdIn,
		MV.AppointmentSerNum,
		MV.Resource,
		MV.ResourceDescription,
		MV.CreationDate,
		MV.Status
	FROM 
		MediVisitAppointmentList MV
	WHERE
		MV.PatientSerNum NOT IN (33651,52641,827,27183,21265,35870,845,44281,44282,44284,44287,44529)
		AND MV.PatientSerNum NOT IN (19900,31049,33274,35775)
		$excludeString
		-- MV.AppointId LIKE '%Pre%'
		AND MV.AppointIdIn != 'InstantAddOn'
		-- AND MV.Status != 'Deleted'
		-- AND MV.AppointmentSerNum IN (5227)
	ORDER BY MV.PatientSerNum
	-- LIMIT 10
	";

my $queryAppointmentList = $dbh->prepare($sqlPatientList) or die("Couldn't prepare statement: ". $dbh->errstr);
$queryAppointmentList->execute() or die("Couldn't execute statement: ". $queryAppointmentList->errstr);

my @partialMatch;
my @potentialMatch;
my @perfectMatch;
my @existingMatch;
my @noMatch;

my $countE = 0;
my $countA = 0;
my $countV = 0;

#for each appointment, check whether the appointment actually belongs to that patient
while(my $dbApp = $queryAppointmentList->fetchrow_hashref())
{
	#convertPreIEId($dbApp,$adtData,$ormsToOacis,$visitData);

	#next;

	#convert the orms patient serial to an Oacis internal id
	my $internalId = $ormsToOacis->{$dbApp->{'PatientSerNum'}};

	my $patient = $adtData->{$internalId};

	my $mvApp = undef;
	my $appType;

	#determine if the AppointId is a visit, dbApp, or encounter id
	if($encounterData->{$dbApp->{'AppointId'}} and $encounterData->{$dbApp->{'AppointId'}}->{'encounterId'} eq $dbApp->{'AppointId'})
	{
		$mvApp = $encounterData->{$dbApp->{'AppointId'}};
		$appType = "Encounter";
		$countE++;
	}
	elsif($appointmentData->{$dbApp->{'AppointId'}} and potentiallyMatched($dbApp,$appointmentData->{$dbApp->{'AppointId'}}))
	{
		$mvApp = $appointmentData->{$dbApp->{'AppointId'}};
		$appType = "Appointment";
		$countA++;
	}
	elsif($visitData->{$dbApp->{'AppointId'}} and potentiallyMatched($dbApp,$visitData->{$dbApp->{'AppointId'}}))
	{
		$mvApp = $visitData->{$dbApp->{'AppointId'}};
		$appType = "Visit";
		$countV++;
	}

	my $matchType;

	#try to match all dbApps, making sure the data in the ORMS db, MediVisit, and the adt match
	if(perfectlyMatched($dbApp,$mvApp,$patient))
	{
		push @perfectMatch, $dbApp->{'AppointmentSerNum'};
		$matchType = "Perfect";
	}
	elsif(partiallyMatched($dbApp,$mvApp,$patient))
	{
		push @partialMatch, $dbApp->{'AppointmentSerNum'};
		$matchType = "Partial";
	}
	elsif(potentiallyMatched($dbApp,$mvApp))
	{
		push @potentialMatch, $dbApp->{'AppointmentSerNum'};
		$matchType = "Potential";
	}
	elsif(checkIfExists($dbApp,$oacisIdToOrms))
	{
		push @existingMatch, $dbApp->{'AppointmentSerNum'};
		$matchType = 'Existing';
	}
	else
	{
		push @noMatch, $dbApp->{'AppointmentSerNum'};
		$matchType = "None";
	}

	#run one of these function at a time to repair entires in the database
	#each function repairs one error
	#convertCurrentIdToEncounterId($dbApp,$mvApp) if($appType eq 'Visit');
	#convertCurrentIdToEncounterId($dbApp,$mvApp) if($appType eq 'Appointment');
	#addCreationDateToAppointment($dbApp,$mvApp) if($matchType eq 'Potential' or $matchType eq 'Perfect');

	#checkIfDuplicateAppointment($dbApp) if($matchType eq 'None');

	#if the match type is potential, the appointment is real but is probably matched to the wrong patient
	#using the mrn and the site of the medivisit appointment, find which patient the appointment really belonged to and update the orms db
	#updatePatientSer($dbApp,$oacisIdToOrms) if($matchType eq "Existing");
	#linkAppointmentToCorrectPatient($dbApp,$mvApp,$adtData,$ormsToOacis) if($matchType eq "Potential");

	#########################################################
	#add ons are created when the patient can't check in to their appointment so it should correspond to an existing appointment -> merge with real appointment

	#when an appointment date/time is changed, the original appointment is deleted and a new one is made
	#however, in medivisit, the appointment doesn't appear as cancelled so we're left with a phantom appointment id in our system

	#update creation date for perfect matches

	#match deleted appointments


}

say $countE;
say $countA;
say $countV;
write_json("noMatch.json",\@noMatch);
write_json("existingMatch.json",\@existingMatch);
write_json("partialMatch.json",\@partialMatch);
write_json("potentialMatch.json",\@potentialMatch);
write_json("perfectMatch.json",\@perfectMatch);

exit;

#######################################
#functions to apply to perfect matches
#######################################

sub convertCurrentIdToEncounterId
{
	my $dbApp = shift;
	my $mvApp = shift;

	return 0 unless($mvApp->{'encounterId'} =~ /A/);

	$dbh->do("
		UPDATE MediVisitAppointmentList
		SET
			AppointId = ?,
			AppointIdIn = ?
		WHERE
			AppointmentSerNum = ?",undef,
		$mvApp->{'encounterId'},
		$mvApp->{'encounterId'},
		$dbApp->{'AppointmentSerNum'}
	) or die("Update error: ". $dbh->errstr);

	return 1;
}

sub convertPreIEId
{
	my $dbApp = shift;
	my $adtData = shift;
	my $ormsToOacis = shift;
	my $visitData = shift;

	#get the visit ids from the oacis data that have the same datetime as the appointment
	my @adtApps = grep {  $_->{'encounterStartDt'} eq $dbApp->{'ScheduledDateTime'} }$adtData->{$ormsToOacis->{$dbApp->{'PatientSerNum'}}}->{'appointments'}->@*;
	my @visitIds = map { $_->{'encounterId'} } @adtApps;

	#for each visit, check if the visit info matches the appointment info
	#if it does, update the appointment Id
	foreach my $visitId (@visitIds)
	{
		if(potentiallyMatched($dbApp,$visitData->{$visitId}))
		{
			my $newAppId = $visitData->{$visitId}->{'encounterId'};
			$newAppId = $visitData->{$visitId}->{'visitId'} if(!$newAppId);

			#if the add on is complete, delete any other entries that would cause a encounterId conflict
			if($dbApp->{'Status'} eq 'Completed')
			{
				$dbh->do("
					DELETE FROM MediVisitAppointmentList
					WHERE AppointId = ?
					",undef,
					$newAppId) or die("Can't delete");
			}

			$dbh->do("
				UPDATE MediVisitAppointmentList
				SET
					AppointId = ?,
					AppointIdIn = ?
				WHERE
					AppointmentSerNum = ?
				",undef,
				$newAppId,
				$newAppId,
				$dbApp->{'AppointmentSerNum'}
			); #or die("PreIE update failed: ". $dbh->errstr);
		}
	}

	return 1;
}

sub addCreationDateToAppointment
{
	my $dbApp = shift;
	my $mvApp = shift;

	if($dbApp->{'CreationDate'} eq '0000-00-00' and $mvApp->{'creationDate'})
	{
		$dbh->do("
			UPDATE MediVisitAppointmentList
			SET
				CreationDate = ?
			WHERE
				AppointmentSerNum = ?",undef,
			$mvApp->{'creationDate'},
			$dbApp->{'AppointmentSerNum'}) or die("CD update error");
	}

	return 1;
}

sub partiallyMatched
{
	my $dbApp = shift;
	my $mvApp = shift;
	my $patient = shift;

	my @matches;

	if($mvApp->{'visitId'})
	{
		#see if the appointment exists in the adt data
		@matches = grep { $_->{'encounterId'} eq $mvApp->{'visitId'} and $_->{'encounterStartDt'} eq $mvApp->{'datetime'} } $patient->{'appointments'}->@*;
		return 1 if(scalar @matches == 1);
	}
	else
	{
		@matches = grep { $_->{'encounterId'} eq $dbApp->{'AppointId'} and $_->{'encounterStartDt'} eq $dbApp->{'ScheduledDateTime'} } $patient->{'appointments'}->@*;
		return 1 if(scalar @matches == 1);
	}

	return 0;
}

sub potentiallyMatched
{
	my $dbApp = shift; #hash ref
	my $mvApp = shift; #hash ref

	#compare the appointment information to what we have in ORMS
	#return ($dbApp->{'ScheduledDateTime'} eq $mvApp->{'datetime'} and $dbApp->{'ResourceDescription'} eq $mvApp->{'resourceDesc'});
	return ($dbApp->{'ScheduledDateTime'} eq $mvApp->{'datetime'} and $dbApp->{'Resource'} eq $mvApp->{'resourceCode'});
}

sub perfectlyMatched
{
	my $dbApp = shift; #hash ref
	my $mvApp = shift; #hash ref
	my $patient = shift; #hash ref

	return (partiallyMatched($dbApp,$mvApp,$patient) and potentiallyMatched($dbApp,$mvApp));
}

sub checkIfExists
{
	my $dbApp = shift;
	my $oacisIdToOrms = shift;

	# my @matches = grep { $_ eq $dbApp->{'PatientSerNum'} } $oacisIdToOrms->{$dbApp->{'AppointId'}}->@*;

	# if(scalar @matches > 1)
	# {
	# 	"Check here";
	# 	return 0;
	# }

	# return 1 if(scalar @matches == 1);

	# return 0;

	return ($oacisIdToOrms->{$dbApp->{'AppointId'}});
}

sub updatePatientSer
{
	my $dbApp = shift;
	my $oacisIdToOrms = shift;

	if(scalar $oacisIdToOrms->{$dbApp->{'AppointId'}}->@* == 1)
	{
		my $correctPatientSer = $oacisIdToOrms->{$dbApp->{'AppointId'}}->[0];

		$dbh->do("
			UPDATE MediVisitAppointmentList
			SET
				PatientSerNum = ?
			WHERE
				AppointmentSerNum = ?
		",undef,
		$correctPatientSer,
		$dbApp->{'AppointmentSerNum'}) or die("Error updating patient ser: ". $dbh->errstr);
	}

	return 1;
}

sub linkAppointmentToCorrectPatient
{
	my $dbApp = shift;
	my $mvApp = shift;
	my $adtData = shift;
	my $ormsToOacis = shift;

	#find the patient associated with the appointment in the orms db
	#db is inconsistent so we can't tell if the id is RVH or MGH
	my $sqlCorrectPatient = "
		SELECT Patient.PatientSerNum
		FROM Patient
		WHERE (Patient.PatientId = ? OR Patient.PatientId_MGH = ?)";

	my $queryCorrectPatient = $dbh->prepare_cached($sqlCorrectPatient) or die("Couldn't prepare statement: ". $dbh->errstr);
	$queryCorrectPatient->execute($mvApp->{'mrn'},$mvApp->{'mrn'}) or die("Couldn't execute statement: ". $queryCorrectPatient->errstr);

	my @patients;
	while(my $ser = $queryCorrectPatient->fetchrow_hashref())
	{
		$ser = $ser->{'PatientSerNum'};
		push @patients, {ser=>$ser, data=> $adtData->{$ormsToOacis->{$ser}}};
	}

	#if we didn't get any patients back from the orms db, then the correct patient doesn't exists in the database
	#insert the patient in the db and rerun the function
	if(!@patients)
	{
		if(createNewPatientInDB($mvApp,$adtData))
		{
			return linkAppointmentToCorrectPatient($dbApp,$mvApp,$adtData,$ormsToOacis);
		}
		else
		{
			return 0;
		}
	}

	#check which patients in the array have the correct mrn
	#we should only get one match
	@patients = grep { $_->{'data'}->{'mrns'}->{'MR_PCS'}->{'mrn'} eq $mvApp->{'mrn'} } @patients if($mvApp->{'site'} eq 'V');
	@patients = grep { $_->{'data'}->{'mrns'}->{'MG_PCS'}->{'mrn'} eq $mvApp->{'mrn'} } @patients if($mvApp->{'site'} eq 'G');

	return 0 if(!@patients or scalar @patients > 1);

	#update the db appointment with the correct patient serial
	$dbh->do("
		UPDATE MediVisitAppointmentList
		SET
			PatientSerNum = ?
		WHERE
			AppointmentSerNum = ?",undef,
		$patients[0]->{'ser'},
		$dbApp->{'AppointmentSerNum'}
	) or die("Update failed: ". $dbh->errstr);

	return 1;
}

sub createNewPatientInDB
{
	my $mvApp = shift;
	my $adtData = shift;

	#if($mvApp->{ssn} =~ /CAMA46571910|LOMA41600914|NORE87531812|CATJ51562914|MCCM960619/)
	#{
		say Dumper($mvApp);
		say "";
		say "";
	#}

	#we don't yet have the patient information in the $adtData so we have to fetch it

	#search the ADT for the patient
	my $requestContent = "
		<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
			<soapenv:Header/>
			<soapenv:Body>
				<pds:findByMrn>
					<mrns>$mvApp->{'mrn'}</mrns>
				</pds:findByMrn>
			</soapenv:Body>
		</soapenv:Envelope>";

	#make the request
	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
	my $response = $ua->request($request);

	return 0 if(!$response->is_success());

	#parse the xml data into an array of hashes
	my @potentialPatients = $xml->xml2hash($response->content(),filter=> '/env:Envelope/env:Body/ns2:findByMrnResponse/return',force_array=> ['mrns'])->@*;

	#remove any duplicates; in the case where a patient has the same mrn for both MGH and RVH, two results are returned
	my %seen;
	@potentialPatients = grep { ! $seen{$_->{'internalId'}} ++ } @potentialPatients;

	#get the oacis patient that matches the mrn and site
	@potentialPatients = grep { grep { $_->{'active'} eq 'true' and $_->{'mrnType'} eq "MR_PCS" and $_->{'mrn'} eq $mvApp->{'mrn'} } $_->{'mrns'}->@*} @potentialPatients if($mvApp->{'site'} eq 'V');
	@potentialPatients = grep { grep { $_->{'active'} eq 'true' and $_->{'mrnType'} eq "MG_PCS" and $_->{'mrn'} eq $mvApp->{'mrn'} } $_->{'mrns'}->@*} @potentialPatients if($mvApp->{'site'} eq 'G');

	return 0 if(!@potentialPatients or scalar @potentialPatients > 1);

	my $matchedPatient = $potentialPatients[0];

	#filter any non active mrns
	$matchedPatient->{'mrns'}->@* = grep { $_->{'active'} eq 'true' } $matchedPatient->{'mrns'}->@*;

	#refactor mrn array to hash with 'MC_ADT','MR_PCS' or 'MG_PCS' keys
	my %mrns = map { $_->{'mrnType'} => $_ } $matchedPatient->{'mrns'}->@*;
	$matchedPatient->{'mrns'} = \%mrns;

	#insert the patient in the database
	my $fname = $matchedPatient->{'firstName'};
	my $lname = $matchedPatient->{'lastName'};
	my $ssn = $matchedPatient->{'ramqNumber'} || '';
	my $ssnExp = substr($matchedPatient->{'ramqExpDate'},2,5) || '';
	$ssnExp =~ s/-//;
	my $patientIdRVH = $matchedPatient->{'mrns'}->{'MR_PCS'}->{'mrn'} || '';
	my $patientIdMGH = $matchedPatient->{'mrns'}->{'MG_PCS'}->{'mrn'} || '';

	if(!$ssn)
	{
		$ssn = $mvApp->{'ssn'} || '';
		$ssnExp = $mvApp->{'ssnExpDate'} || '';
	}

	if(!$ssn)
	{
		say Dumper($mvApp);
	}

	$dbh->do("
		INSERT INTO Patient(LastName,FirstName,SSN,SSNExpDate,PatientId,PatientId_MGH)
		VALUES(?,?,?,?,?,?)",undef,
	$lname,
	$fname,
	$ssn,
	$ssnExp,
	$patientIdRVH,
	$patientIdMGH) or return 0;

	my $patientSer = $dbh->last_insert_id(undef,undef,'Patient','PatientSerNum') or return 0;

	#insert the new patient info into the adtData object
	$adtData->{$matchedPatient->{'internalId'}} = $matchedPatient;
	$ormsToOacis->{$patientSer} = $matchedPatient->{'internalId'};

	return 1;
}

sub checkIfDuplicateAppointment
{
	my $dbApp = shift;


}

###################################
# subroutines
###################################
#create three hashes with all appointment information and save it to a json file
#one hash has the visit id as the key
#another has the appointment id as the key
#the last has the full encounter id as the key
sub createAndSaveHashes
{
	#read the list of medivisit visits from the csv file
	#we're assuming the csv file will be encoded in utf8;
	use utf8;

	#key is appointment 8 digit appointment serial
	my $visitData = {};
	my $appointmentData = {};
	my $encounterData = {};
	my @visits = ();

	#open csv files and put everything into an array
	foreach my $csvFileName("./appointments/ortho_2012-2019.csv","./appointments/glen_2012-2019.csv","./appointments/ortho_app_2019-2021.csv","./appointments/glen_app_2019-2021.csv")
	#foreach my $csvFileName ("./appointments/test.csv")
	{
		push @visits, csv(in=> $csvFileName, headers=> "auto")->@* or die("file error");
	}

	#create a hash where the keys are the appointment ids
	for (@visits)
	{
		my $visitId = $_->{'Séq Visite'};
		my $appointmentId = $_->{'Séq RV'};
		my $encounterId = '';
		$encounterId = substr($_->{'DH Cré RV Visite'},0,4) ."A". $_->{'Séq RV'} if($_->{'DH Cré RV Visite'});
		$encounterId = substr($_->{'DH Cré RV'},0,4) ."A". $_->{'Séq RV'} if($_->{'DH Cré RV'});

		#check if any of the ids already exist
		#in the case of appointment and encounter ids, if they do and the visit id is larger, don't overwrite the current data
		if($visitData->{$visitId})
		{
			say "$visitId visit already seen";
		}

		if($appointmentData->{$appointmentId})
		{
			say "$appointmentId appointment already seen";
			$appointmentId = '';
		}

		if($encounterData->{$encounterId})
		{
			say "$encounterId encounter already seen";
			$encounterId = '';
		}

		my $data = {
			appointmentId=> $_->{'Séq RV'},
			creationDate=> $_->{'DH Cré RV Visite'},
			date=> $_->{'Date Visite'},
			datetime=> $_->{'Date Visite'} ." ". $_->{'Heure Visite'} .":00",
			encounterId=> $encounterId,
			mrn=> $_->{'No Dossier'},
			resourceCode=> $_->{'Code Aff Clinique'},
			resourceDesc=> $_->{'Desc Clinique'},
			site=> $_->{'Code Aff Type Dossier'},
			ssn=> $_->{'NAM'},
			ssnExpDate=> $_->{'Date Exp NAM'},
			time=> $_->{'Heure Visite'},
			visitId=> $_->{'Séq Visite'}
		};

		$data = {
			appointmentId=> $_->{'Séq RV'},
			creationDate=> $_->{'DH Cré RV'},
			date=> $_->{'Date RV'},
			datetime=> $_->{'Date RV'} ." ". $_->{'Heure RV'} .":00",
			encounterId=> $encounterId,
			mrn=> $_->{'No Dossier'},
			resourceCode=> $_->{'Code Aff Clinique'},
			resourceDesc=> $_->{'Desc Clinique'},
			site=> $_->{'Code Aff Type Dossier'},
			ssn=> $_->{'NAM'},
			ssnExpDate=> $_->{'Date Exp NAM'},
			time=> $_->{'Heure RV'},
			visitId=> ''
		} if($_->{'DH Cré RV'});

		#insert zeros for incomplete MRNs
		while(length($data->{'mrn'}) < 7)
		{
			$data->{'mrn'} = '0'. $data->{'mrn'};
		}

		$visitData->{$visitId} = $data if($visitId);
		$appointmentData->{$appointmentId} = $data if($appointmentId);
		$encounterData->{$encounterId} = $data if($encounterId);
	}

	no utf8;

	write_json("visits.json",$visitData);
	write_json("appointments.json",$appointmentData);
	write_json("encounters.json",$encounterData);
}