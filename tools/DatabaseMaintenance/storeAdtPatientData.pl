#!/usr/bin/perl

#-----------------------------------------------
# Script that queries the ORMS database for all patients and finds the patient in the hospital ADT.
# Saves all data obtained from the ADT and the ORMS db in a file for further processing.
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
#use warnings;
use v5.16;

#use Cwd qw(abs_path);

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

use Data::Dumper;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Hash::XS->new();

my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request

#construct a hash with Oacis internal Ids as the keys and hash containing ADT data as the values
#also create a hash that maps orms db a patientSer to an Oacis internal Id
my $adtData; #hash
my $ormsToOacis; #hash
my $oacisIdToOrms; #hash

#read the adt data object from previous runs
$adtData = read_json('adtData.json');
$ormsToOacis = read_json('ormsToOacis.json');
$oacisIdToOrms = read_json("oacisIdToOrms.json");

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
#my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#custom connection
use DBI;
my $dbh = DBI->connect_cached("DBI:MariaDB:database=WaitRoomManagement;host=172.26.66.41",'readonly','readonly') or die("Can't connect");

#get a list of all the patients in the database
my $sqlPatientList = "
	SELECT
		Patient.FirstName,
		Patient.LastName,
		Patient.PatientId,
		Patient.PatientId_MGH,
		Patient.PatientSerNum
	FROM
		Patient
	WHERE
		Patient.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC')
		AND Patient.PatientId NOT LIKE 'Opal%'

	ORDER BY Patient.LastName,Patient.FirstName,Patient.PatientId
	-- LIMIT 1
	";

my $queryPatientList = $dbh->prepare($sqlPatientList) or die("Couldn't prepare statement: ". $dbh->errstr);
$queryPatientList->execute() or die("Couldn't execute statement: ". $queryPatientList->errstr);

my @unknownPatients;
my $count = 0;

while(my $data = $queryPatientList->fetchrow_hashref)
{
	$count++;
	if($count % 100 == 0) {say $count;}

	#skip the information fetching if we already found the patient in previous runs
	next if($adtData->{$ormsToOacis->{$data->{'PatientSerNum'}}});

	my $patient = getPatientInformationFromAdt($data);

	if(!defined $patient)
	{
		push @unknownPatients, $data->{'PatientSerNum'};
		next;
	}

	#get the list of appointments that the patient had
	$patient->{'appointments'}->@* = getAppointmentInformationFromAdt($patient);

	if($adtData->{$patient->{'internalId'}})
	{
		say "$patient->{'internalId'} | $data->{'PatientId'} | $data->{'PatientId_MGH'}";
	}

	$adtData->{$patient->{'internalId'}} = $patient;
	$ormsToOacis->{$data->{'PatientSerNum'}} = $patient->{'internalId'};

	#create a dictionary of oacis appointment/visit IDs to ORMS patient ser
	for($patient->{'appointments'}->@*)
	{
		# if($oacisIdToOrms->{$_->{'encounterId'}})
		# {
		# 	say "$_->{'encounterId'} | $oacisIdToOrms->{$_->{'encounterId'}} | $data->{'PatientSerNum'}";
		# }

		#$oacisIdToOrms->{$_->{'encounterId'}} = $data->{'PatientSerNum'};

		push $oacisIdToOrms->{$_->{'encounterId'}}->@*, $data->{'PatientSerNum'};
	}
}

#convert the ADT and db patient data to json objects and store them in a file
write_json("adtData.json",$adtData);
write_json("ormsToOacis.json",$ormsToOacis);
write_json("oacisIdToOrms.json",$oacisIdToOrms);
write_json("unknown.json",\@unknownPatients);

exit;


###################################
# subroutines
###################################

#for an orms patients, get all patients in the hospital ADT that share an mrn with those patients and matches the orms patient to one adt patient
#returns a hash reference with the ADT patient information
sub getPatientInformationFromAdt
{
	my $ormsPatient = shift; #hash ref

	my $searchMrn = $ormsPatient->{'PatientId'} || $ormsPatient->{'PatientId_MGH'};

	#search the ADT for the patient
	my $requestContent = "
		<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
			<soapenv:Header/>
			<soapenv:Body>
				<pds:findByMrn>
					<mrns>$searchMrn</mrns>
				</pds:findByMrn>
			</soapenv:Body>
		</soapenv:Envelope>";

	#make the request
	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
	my $response = $ua->request($request);

	#check if the request failed and return an empty array if it did
	return undef if(!$response->is_success());

	#parse the xml data into an array of hashes
	my @potentialPatients = $xml->xml2hash($response->content(),filter=> '/env:Envelope/env:Body/ns2:findByMrnResponse/return',force_array=> ['mrns'])->@*;

	#remove any duplicates; in the case where a patient has the same mrn for both MGH and RVH, two results are returned
	my %seen;
	@potentialPatients = grep { ! $seen{$_->{'internalId'}} ++ } @potentialPatients;

	#get the oacis patient that matches the input patient
	@potentialPatients = grep { flmMatch($ormsPatient,$_) } @potentialPatients;

	return undef if(!@potentialPatients or scalar @potentialPatients > 1);

	my $matchedPatient = $potentialPatients[0];

	#filter any non active mrns
	$matchedPatient->{'mrns'}->@* = grep { $_->{'active'} eq 'true' } $matchedPatient->{'mrns'}->@*;

	#refactor mrn array to hash with 'MC_ADT','MR_PCS' or 'MG_PCS' keys
	my %mrns = map { $_->{'mrnType'} => $_ } $matchedPatient->{'mrns'}->@*;
	$matchedPatient->{'mrns'} = \%mrns;

	return $matchedPatient;
}

# Firstname, Lastname, MRN match
#Compares an Orms patient and an ADT entry by trying to match their first and last names, and an mrn
#Returns 1 on match, 0 otherwise
sub flmMatch
{
	my $ormsPatient = shift; #hash ref
	my $adtPatient = shift; #hash ref

	#compare first name
	return 0 if($ormsPatient->{'FirstName'} ne $adtPatient->{'firstName'});

	#compare last name
	if($ormsPatient->{'LastName'} ne $adtPatient->{'lastName'})
	{
		#if last names don't match, there's a possibility we're not looking at the right last name
		#for example, a patient marries and changes their last name
		#we can try using the 'otherName' field
		#return 0 if($ormsPatient->{'LastName'} ne $adtPatient->{'otherName'});

		return 0;
	}

	#get all MRNs that match
	my @mrnMatches = grep {$_->{'mrn'} eq $ormsPatient->{'PatientId'} and $_->{'mrnType'} ne "MC_ADT" and $_->{'active'} eq 'true' } $adtPatient->{'mrns'}->@*;

	#if no match if found, it's possible that the 'PatientId' value is empty and the patient only has a MGH mrn
	@mrnMatches = grep {$_->{'mrn'} eq $ormsPatient->{'PatientId_MGH'} and $_->{'mrnType'} ne "MC_ADT" and $_->{'active'} eq 'true' } $adtPatient->{'mrns'}->@* if(!@mrnMatches);

	return 0 if(!@mrnMatches);

	#it is possible the patient has the same mrn for both the MUHC and the MGH
	if(scalar @mrnMatches > 1)
	{
		my $mrnRVH = (grep { $_->{'mrnType'} eq 'MR_PCS' and $_->{'active'} eq 'true' } @mrnMatches)[0];
		my $mrnMGH = (grep { $_->{'mrnType'} eq 'MG_PCS' and $_->{'active'} eq 'true' } @mrnMatches)[0];

		return 0 unless($mrnRVH->{'mrn'} eq $mrnMGH->{'mrn'});
	}

	return 1;
}

#
sub getAppointmentInformationFromAdt
{
	my $adtPatient = shift; #hash ref

	#use the RVH mrn by default
	#if they don't have one, use the MGH mrn
	#if they have none, return nothing
	my $mrn = $adtPatient->{'mrns'}->{'MR_PCS'}->{'mrn'} || $adtPatient->{'mrns'}->{'MG_PCS'}->{'mrn'};
	my $mrnType = $adtPatient->{'mrns'}->{'MR_PCS'}->{'mrnType'} || $adtPatient->{'mrns'}->{'MG_PCS'}->{'mrnType'};

	return undef if(!$mrn or !$mrnType);

	my $requestContent = "
		<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
			<soapenv:Header/>
				<soapenv:Body>
					<pds:getPatientEncounters>
						<mrn>$mrn</mrn>
						<extAppId>$mrnType</extAppId>
						<encStartDtm>2000-01</encStartDtm>
						<encEndDtm>2030-01</encEndDtm>
					</pds:getPatientEncounters>
				</soapenv:Body>
		</soapenv:Envelope>";

	#make the request
	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
	my $response = $ua->request($request);

	#check if the request failed and return an empty array if it did
	return () if(!$response->is_success());

	#parse the xml data into an array of hashes
	my @appointments = $xml->xml2hash($response->content(),filter=> '/env:Envelope/env:Body/ns2:getPatientEncountersResponse/return')->@*;

	return () if(!@appointments);

	#for each appointment found, parse the time into the same format as the orms db
	#also convert YYYYCX to YYYYAX
	for(@appointments)
	{
		#remove everything after the seconds
		$_->{'encounterStartDt'} =~ s/\..+$//;
		$_->{'encounterEndDt'} =~ s/\..+$// if($_->{'encounterEndDt'});

		#remove the 'T'
		$_->{'encounterStartDt'} =~ s/T/ /;
		$_->{'encounterEndDt'} =~ s/T/ / if($_->{'encounterEndDt'});

		#convert Cs to as
		$_->{'encounterId'} =~ s/C/A/;
	}

	#remove any duplicates
	my %seen;
	@appointments = grep { ! $seen{$_->{'encounterId'}} ++ } @appointments;

	return @appointments;
}