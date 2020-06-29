#!/usr/bin/perl

#-----------------------------------------------
# Script that updates the orms db with the proper first and last names from the hospital ADT
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
use warnings;
use v5.16;

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

while(my $data = $queryPatientList->fetchrow_hashref())
{
	#for each patient, search for a patient match in the ADT using the ramq and the mrn

	my $originalSSN = $data->{'SSN'};

	my $patient = matchInAdt($data);

	#if nothing was found, try with the ramq uppercased
	if(!defined $patient)
	{
		$data->{'SSN'} = uc $data->{'SSN'};
		$patient = matchInAdt($data);
	}

	next if(!defined $patient);

	#say "$data->{'LastName'} -> $patient->{'lastName'} | $data->{'FirstName'} -> $patient->{'firstName'} | $originalSSN -> $patient->{'ramqNumber'}";

	my $updateResult = updateInOrmsDb($data->{'PatientSerNum'},$patient);

	push @resultStrings, "result for serial $data->{'PatientSerNum'} : $updateResult";
}

write_json("flSuccess.json",\@resultStrings);

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

	#remove any duplicates; in the case where a patient has the same mrn for both MGH and RVH, two results are returned
	my %seen;
	@potentialPatients = grep { ! $seen{$_->{'internalId'}} ++ } @potentialPatients;

	#get the oacis patient that matches the input patient
	@potentialPatients = grep { rmMatch($ormsPatient,$_) } @potentialPatients;

	return undef if(!@potentialPatients or scalar @potentialPatients > 1);

	my $matchedPatient = $potentialPatients[0];

	return $matchedPatient;
}

# RAMQ, MRN match
#Compares an Orms patient and an ADT entry by trying to match their ramqs, and an mrn
#Returns 1 on match, 0 otherwise
sub rmMatch
{
	my $ormsPatient = shift; #hash ref
	my $adtPatient = shift; #hash ref

	#compare the ramq
	return 0 if($ormsPatient->{'SSN'} ne $adtPatient->{'ramqNumber'});

	#gets alls MRNs that match
	my @mrnMatches = grep {$_->{'mrn'} eq $ormsPatient->{'PatientId'} and $_->{'mrnType'} ne "MC_ADT" and $_->{'active'} eq 'true' } $adtPatient->{'mrns'}->@*;

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
			SSN = UPPER(SSN)
		WHERE
			PatientSerNum = ?",undef,$adtPatient->{'firstName'},$adtPatient->{'lastName'},$patientSer);

	return "Update failed" if(!defined $updatePatientResult or $updatePatientResult eq '0E0');

	return "success";
}