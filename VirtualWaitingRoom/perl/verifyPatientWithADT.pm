#!/usr/bin/perl
######################################################################
#package that verifies if a patient exists in the hospital ADT and that the information given matches the ADT
####################################################################
package verifyPatientWithADT;

use strict;
use v5.26;
use lib "./";

use HTTP::Request::Common;
use LWP::UserAgent;
use XML::Simple;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Simple->new();

#some ids to test the function
#my $result = patientExists('','5442751','RV','JOYCE','BEAUCAGE');
#say $result;

#returns 1 if all patient parameters match one found in the ADT and 0 if it isn't
#anything else indicates an error
sub patientExists
{
	my $self = $_[0];
	my $patientId = $_[1];
	my $idType = $_[2];
	my $fname = $_[3];
	my $lname = $_[4];

	#the ADT has a specific format for RVH and MGH ids
	my $expectedType = "";
	$expectedType = "MG_PCS" if($idType eq "MG");
	$expectedType = "MR_PCS" if($idType eq "RV");
	#$expectedType = "MC_ADT" what this is for is unknown

	my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
	my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request
	my $requestContent = #the ADT request message
		"<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
			<soapenv:Header/>
			<soapenv:Body>
				<pds:findByMrn>
					<mrns>$patientId</mrns>
				</pds:findByMrn>
			</soapenv:Body>
		</soapenv:Envelope>";

	my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;

	my $response = $ua->request($request); #make the request and get the response back

	#check if the request failed
	#if(!$response->is_success()) {return "Connection failed.";}
	if(!$response->is_success()) {return -10;}

	#parse the xml data into a perl readable format
	my %data = %{$xml->XMLin($response->content())};

	#check if the response is empty (no patient associated with the ramq), there is one match (a hash), or multiple matches (an array)
	my $data = $data{'env:Body'}{'ns2:findByMrnResponse'}{'return'}; #the reference

	#if(!ref($data)) {return "There is no patient associated with that RAMQ.";}
	if(!ref($data)) {return -1;}
	elsif(ref($data) eq 'HASH')
	{
		my %info = %{$data};

		#verify if parameters match
		my $match = 0;

		if(uc($info{'firstName'}) eq uc($fname) and uc($info{'lastName'}) eq uc($lname))
		{
			if(ref($info{'mrns'}) eq 'HASH')
			{
				$match = 1 if($info{'mrn'} eq $patientId and $info{'mrnType'} eq $expectedType);
			}
			elsif(ref($info{'mrns'}) eq 'ARRAY')
			{
				foreach my $mrn (@{$info{'mrns'}})
				{
					$match = 1 if($mrn->{'mrn'} eq $patientId and $mrn->{'mrnType'} eq $expectedType);
				}
			}
			else {return -15;}
		}

		if($match) {return 1;}
		else {return 0;}
	}
	elsif(ref($data) eq 'ARRAY')
	{
		#if multiple patients are matched from the patientId, check if at least one matches the input parameters
		#if it does, then we know we have the correct information for a patient

		my @patients = @{$data};
		my $match = 0;

		foreach my $patient (@patients)
		{
			my %info = %{$patient};

			if(uc($info{'firstName'}) eq uc($fname) and uc($info{'lastName'}) eq uc($lname))
			{
				if(ref($info{'mrns'}) eq 'HASH')
				{
					$match = 1 if($info{'mrn'} eq $patientId and $info{'mrnType'} eq $expectedType);
				}
				elsif(ref($info{'mrns'}) eq 'ARRAY')
				{
					foreach my $mrn (@{$info{'mrns'}})
					{
						$match = 1 if($mrn->{'mrn'} eq $patientId and $mrn->{'mrnType'} eq $expectedType);
					}
				}
				else {return -15;}
			}
		}

		if($match) {return 1;}
		else {return 0;}
	}

	#if the code somehow got to this step, some possibility wasn't considered
	#return "Error";
	return -20;
}

1;
