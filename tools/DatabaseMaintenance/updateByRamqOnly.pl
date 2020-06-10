#!/usr/bin/perl

#-----------------------------------------------
# Script that updates orms db patients using the RAMQ
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
use warnings;
use v5.26;

use Cwd qw(abs_path);

my $runningDir;
BEGIN {
	$runningDir = abs_path(__FILE__ ."/../");
}

use lib "$runningDir/../../perl/system/modules/";
use LoadConfigs;
use HTTP::Request::Common;
use LWP::UserAgent;
use XML::Hash::XS;
use File::JSON::Slurper qw(read_json write_json);

use Data::Dumper;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Hash::XS->new();

my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request

#read the list of patient serials to be updated
my @unknowns = read_json('unknown.json')->@*;

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
#my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#custom connection
use DBI;
my $dbh = DBI->connect_cached("DBI:MariaDB:database=WaitRoomManagement;host=172.26.66.41",'readonly','readonly') or die("Can't connect");

my $listString = join(",",@unknowns);
$listString = "($listString)";

#get a list of all the patients in the database
my $sqlPatientList = "
	SELECT
		Patient.FirstName,
		Patient.LastName,
		Patient.PatientId,
		Patient.PatientId_MGH,
		Patient.SSN,
		Patient.SSNExpDate,
		Patient.PatientSerNum
	FROM
		Patient
	WHERE
		Patient.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC','')
		AND Patient.PatientId NOT LIKE 'Opal%'
		AND Patient.PatientId NOT LIKE 'Unk%'
		AND Patient.PatientSerNum IN $listString
	ORDER BY Patient.LastName,Patient.FirstName,Patient.PatientId
	-- LIMIT 1
	";

my $queryPatientList = $dbh->prepare($sqlPatientList) or die("Couldn't prepare statement: ". $dbh->errstr);
$queryPatientList->execute() or die("Couldn't execute statement: ". $queryPatientList->errstr);

my @resultStrings;
my @oldInfo;

while(my $data = $queryPatientList->fetchrow_hashref())
{
	#for each patient, search for a unambiguous patient match in the ADT using only the ramq

	my $originalSSN = $data->{'SSN'};

	my $patient = matchInAdt($data);

	#if nothing was found, try with the ramq uppercased
	if(!defined $patient)
	{
		$data->{'SSN'} = uc $data->{'SSN'};
		$patient = matchInAdt($data);
	}

	next if(!defined $patient);

	#since we're updating everything besides the ramq, it would be prudent to save the old information
	my $mrnStringRVH = "$data->{'PatientId'} -> $patient->{'mrns'}->{'MR_PCS'}->{'mrn'}";
	my $mrnStringMGH = "$data->{'PatientId_MGH'} -> $patient->{'mrns'}->{'MG_PCS'}->{'mrn'}";
	my $ramqString = "$originalSSN -> $patient->{'ramqNumber'}";
	my $nameString = "L: $data->{'LastName'} -> $patient->{'lastName'} | F: $data->{'FirstName'} -> $patient->{'firstName'}";

	my $mrnChangeRVH = ($data->{'PatientId'} ne $patient->{'mrns'}->{'MR_PCS'}->{'mrn'}) ? 1 : 0;
	my $mrnChangeMGH = ($data->{'PatientId_MGH'} ne $patient->{'mrns'}->{'MG_PCS'}->{'mrn'}) ? 1 : 0;
	my $ramqChange = ($originalSSN ne $patient->{'ramqNumber'}) ? 1 : 0;
	my $nameChange = ($data->{'LastName'} ne $patient->{'lastName'} or $data->{'FirstName'} ne $patient->{'firstName'}) ? 1 : 0;

	my $changes = {
		mrnChangeRVH=> $mrnChangeRVH,
		mrnChangeMGH=> $mrnChangeMGH,
		ramqChange=> $ramqChange,
		nameChange=> $nameChange,
		mrnStringRVH=> $mrnStringRVH,
		mrnStringMGH=> $mrnStringMGH,
		ramqString=> $ramqString,
		nameString=> $nameString
	};

	push @oldInfo, $changes;

	my $updateResult = updateInOrmsDb($data->{'PatientSerNum'},$patient);

	push @resultStrings, "result for serial $data->{'PatientSerNum'} : $updateResult";
}

write_json("oldPatientInfo.json",\@oldInfo);
write_json("ramqSuccess.json",\@resultStrings);

exit;

###################################
# subroutines
###################################

#for an orms patients, get all patients in the hospital ADT that share an mrn with those patients and matches the orms patient to one adt patient
#returns a hash reference with the adt patient information
sub matchInAdt
{
	my $ormsPatient = shift; #hash ref

	#search the ADT for the patient
	my $requestContent = #the ADT request message
		"<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
			<soapenv:Body>
				<pds:findByRamq>
					<ramqs>$ormsPatient->{'SSN'}</ramqs>
				</pds:findByRamq>
			</soapenv:Body>
		</soapenv:Envelope>";

	#make the request
	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
	my $response = $ua->request($request);

	#check if the request failed and return an empty array if it did
	return undef if(!$response->is_success());

	#parse the xml data into an array of hashes
	my @potentialPatients = $xml->xml2hash($response->content(),filter=> '/env:Envelope/env:Body/ns2:findByRamqResponse/return',force_array=> ['mrns'])->@*;

	#remove any newborns from the list, usually they get the ramq of the mother
	@potentialPatients = grep { $_->{'maritalStatus'} ne 'NewBorn' } @potentialPatients;

	#remove any duplicates; in the case where a patient has the same mrn for both MGH and RVH, two results are returned
	my %seen;
	@potentialPatients = grep { ! $seen{$_->{'internalId'}} ++ } @potentialPatients; #perl magic

	#in order for the match to be unambiguous, there must be only one patient in the ADT associated with a specific ramq
	return undef if(!@potentialPatients or scalar @potentialPatients > 1);

	#get the oacis patient that matches the input patient
	@potentialPatients = grep { rMatch($ormsPatient,$_) } @potentialPatients;

	return undef if(!@potentialPatients or scalar @potentialPatients > 1);

	my $matchedPatient = $potentialPatients[0];

	#filter any non active mrns
	$matchedPatient->{'mrns'}->@* = grep { $_->{'active'} eq 'true' } $matchedPatient->{'mrns'}->@*;

	#refactor mrn array to hash with 'MC_ADT','MR_PCS' or 'MG_PCS' keys
	my %mrns = map { $_->{'mrnType'} => $_ } $matchedPatient->{'mrns'}->@*;
	$matchedPatient->{'mrns'} = \%mrns;

	return $matchedPatient;
}

# RAMQ match
#Compares an Orms patient and an ADT entry with the ramq
#Returns 1 on match, 0 otherwise
sub rMatch
{
	my $ormsPatient = shift; #hash ref
	my $adtPatient = shift; #hash ref

	#compare the ramq
	return 0 if($ormsPatient->{'SSN'} ne $adtPatient->{'ramqNumber'});

	return 1;
}

#updates a patient's first name and last name in the orms db
sub updateInOrmsDb
{
	my $patientSer = shift;
	my $adtPatient = shift;
	my $uppercaseRamq = shift;

	my $updatePatientResult = $dbh->do("
		UPDATE Patient
		SET
			FirstName = ?,
			LastName = ?,
			PatientId = ?,
			PatientId_MGH = ?,
			SSN = UPPER(SSN)
		WHERE
			PatientSerNum = ?",undef,$adtPatient->{'firstName'},$adtPatient->{'lastName'},$adtPatient->{'mrns'}->{'MR_PCS'}->{'mrn'},$adtPatient->{'mrns'}->{'MG_PCS'}->{'mrn'},$patientSer);

	return "Update failed" if(!defined $updatePatientResult or $updatePatientResult eq '0E0');

	return "success";
}