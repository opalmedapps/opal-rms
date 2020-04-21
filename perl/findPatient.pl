#!/opt/perl5/perl

# FindByRAMQ: Simple script taking a patient RAMQ and returning that patient's info

#------------------------------------------------------------------------
# V.Matassa Jul 2019
# K.Agnew Oct 2018
#------------------------------------------------------------------------

#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
use strict;
use v5.30;
use lib "./system/modules";
#use warnings;
#se diagnostics;

#------------------------------------------------------------------------
# Use the DBI module
#------------------------------------------------------------------------
use LoadConfigs;
use Data::Dumper;
use CGI qw(:standard);
use JSON;
use Time::Piece;
#------------------------------------------------------------------------
# Modules needed for SOAP webservices
#------------------------------------------------------------------------
use LWP::UserAgent;
#use LWP::Simple;
use HTTP::Request::Common;
use XML::Simple;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Simple->new();

#------------------------------------------------------------------------
# Internal Variables
#------------------------------------------------------------------------
my $verbose = 0;
my $datafile;
my $AppointSys;

#------------------------------------------------------------------------
# Read in the command line arguments
#------------------------------------------------------------------------
my $ramq = param("ramq");
my $pid = param("pid");
my $speciality = param("speciality");

$speciality = "MR_PCS" if($speciality eq "Oncology");
$speciality = "MG_PCS" if($speciality eq "Ortho");

#determine whether to search for a patient using the pid or the ramq
my $mode = "PID";
$mode = "RAMQ" if($ramq);

#begin feedback
print "Content-type: application/json\n\n";

#connect to database
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#format sql query based on the input mrn
my $searchCondition = " PatientId = '$pid' ";
$searchCondition = " SSN = '$ramq' " if($mode eq "RAMQ");

my $sql="
	SELECT
		LastName,
		FirstName,
		SSN,
		SSNExpDate,
		PatientId
	FROM
		Patient
	WHERE
		$searchCondition
	";
my $query = $dbh->prepare($sql) or die "Couldn't prepare statement: " . $dbh->errstr;
$query->execute() or die "Couldn't execute statement: " . $query->errstr;

my $data = $query->fetchall_arrayref();

my $json_str = JSON->new->allow_nonref;
$json_str->convert_blessed(1);
my $nextRow;
my $newRec;
my $json_data;

sub convertAdtDataToJsonFormattedArray
{
	my $mainData = shift;

	my $last = $mainData->{'lastName'};
	my $first = $mainData->{'firstName'};
	my $ramq = $mainData->{'ramqNumber'};
	my $ramqExp = substr($mainData->{'ramqExpDate'},2,5);

	my $pid = $mainData->{'mrns'}->[0]->{'mrn'};

	$ramqExp =~ s/-//g;
	print "Info retrieved: $last $first $ramq $ramqExp $pid \n\n" if $verbose;

	#format as JSON and return
	my $data = {
		last => $last,
		first => $first,
		ramq => $ramq,
		ramqExp => $ramqExp,
		pid => $pid,
	};

	return $data;
};

#We must cover the case where a patient mrn doesnt exist in the WaitRoomManagement database
#In this case use the ADT script written by John to get the info viewable in SOAP

my $pdsFunction = "
	<pds:findByMrn>
		<mrns>$pid</mrns>
	</pds:findByMrn>
";

$pdsFunction = "
	<pds:findByRamq>
		<ramqs>$ramq</ramqs>
	</pds:findByRamq>
" if($mode eq "RAMQ");

if(!($query->rows)){ #Patient not already in database
	print "Patient not found in WaitRoomManagement\n" if $verbose;
	my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #location for xml request
	my $requestType = 'text/xml; charset=utf-8'; #char encoding
	my $requestContent = "
	<soapenv:Envelope soap:encodingStyle='http://schemas.xmlsoap.org/soap/encoding/' xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/' xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/' xmlns:soapenc='http://schemas.xmlsoap.org/soap/encoding/' xmlns:xsd='http://www.w3.org/2001/XMLSchema' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
		   <soapenv:Body>
			$pdsFunction
		   </soapenv:Body>
		</soapenv:Envelope>
	";

	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
	my $response = $ua->request($request);
	#check for response failure
	if(!$response->is_success()) {
		print "Response failed\n" if $verbose;
        exit();
		#return -10;
	}

	#parse data
	my %xml_data = %{$xml -> XMLin($response->content())};
	#select data from xml body
	my $mainData = $xml_data{'env:Body'}{'ns2:findByMrnResponse'}{'return'};
	$mainData = $xml_data{'env:Body'}{'ns2:findByRamqResponse'}{'return'} if($mode eq "RAMQ");

	#check if body empty
	if(!ref($mainData)){
		print "Body of data empty \n" if $verbose;
		#return -1;
	}
	elsif(ref($mainData) eq 'ARRAY'){ #two patients with same ramq (one is probably very old)
		my @patients = @{$mainData};
		my $dateLimit = Time::Piece->strptime('1999-12-31','%Y-%m-%d');
		foreach my $patient (@patients)
		{
			my %pat = %{$patient};
			if(Time::Piece->strptime(substr($pat{'ramqExpDate'},0,10), '%Y-%m-%d') <= $dateLimit){
				$patient = '';
			}
		}
		@patients = grep{$_ ne ''} @patients; #remove the empty pats

		#filter mrns and get the one that matches
		foreach my $patient (@patients)
		{
			if(ref($patient->{'mrns'}) eq 'ARRAY') {
				$patient->{'mrns'}->@* = grep { $_->{'mrnType'} eq $speciality and $_->{'mrn'} eq $pid } $patient->{'mrns'}->@*;
			}
			else {
				$patient->{'mrns'} = [grep { $_->{'mrnType'} eq $speciality and $_->{'mrn'} eq $pid } ($patient->{'mrns'})];
			}
			next if(scalar $patient->{'mrns'}->@* == 0);

			push @{$json_data}, convertAdtDataToJsonFormattedArray($patient);
		}
		my %seen;
		$json_data = [grep {!$seen{$_->{'pid'}}++} $json_data->@*]; #filter duplicates
		my $JSON = $json_str->encode({"record" => $json_data});
		print "$JSON\n";

	}
	elsif(ref($mainData) eq 'HASH'){
		if(ref($mainData->{'mrns'}) eq 'ARRAY') {
			$mainData->{'mrns'}->@* = grep { $_->{'mrnType'} eq $speciality and $_->{'mrn'} eq $pid } $mainData->{'mrns'}->@*;
		}
		else {
			$mainData->{'mrns'} = [grep { $_->{'mrnType'} eq $speciality and $_->{'mrn'} eq $pid } ($mainData->{'mrns'})];
		}
		exit if(scalar $mainData->{'mrns'}->@* == 0);

		push @{$json_data}, convertAdtDataToJsonFormattedArray($mainData);

		my $JSON = $json_str->encode({"record" => $json_data});
		print "$JSON\n";
	}
}
else{ #Format JSON and return as normal
	foreach my $x (@$data)
	{
		push @{$json_data} , {
			last => $x->[0],
			first => $x->[1],
			ramq => $x->[2],
			ramqExp => $x->[3],
			pid => $x->[4]
		};
	}
	my $JSON = $json_str->encode({"record" => $json_data});
	print "$JSON\n";

}

$query->finish;
$dbh->disconnect;
exit;

