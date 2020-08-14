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
use v5.26;

#use Cwd qw(abs_path);

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

#read data from previous runs
my $ormsToOacis = read_json("./data/ormsToOacis.json");

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
#my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#custom connection
use DBI;
my $dbh = DBI->connect_cached("DBI:MariaDB:database=;host=",'','') or die("Can't connect");

#get a list of all the patients in the database
my $sqlPatientList = "
    SELECT
        Patient.FirstName,
        Patient.LastName,
        Patient.SSN,
        Patient.PatientId,
        Patient.PatientId_MGH,
        Patient.PatientSerNum
    FROM
        Patient
    WHERE
        Patient.PatientId NOT IN ('9999991','9999994','9999995','9999996','9999997','9999998','CCCC')
        AND Patient.PatientId NOT LIKE 'Opal%'
        AND Patient.PatientId != ''
    ORDER BY Patient.LastName,Patient.FirstName,Patient.PatientId
    -- LIMIT 100
";

my $queryPatientList = $dbh->prepare($sqlPatientList) or die("Couldn't prepare statement: ". $dbh->errstr);
$queryPatientList->execute() or die("Couldn't execute statement: ". $queryPatientList->errstr);

my @unknownPatients;
my $count = 0;

while(my $data = $queryPatientList->fetchrow_hashref)
{
    $count++;
    say $count if($count % 100 == 0);

    #skip the information fetching if we already found the patient in previous runs
    next if($ormsToOacis->{$data->{'PatientSerNum'}});

    my $patient = getPatientInformationFromAdt($data);

    if(!defined $patient)
    {
        push @unknownPatients, $data->{'PatientSerNum'};
        next;
    }

    #get the mrn type of the mrn stored in orms
    $patient->{"mrns"} = [grep { $_->{"mrn"} eq $data->{"PatientId"} } $patient->{"mrns"}->@*];

    if(scalar $patient->{"mrns"}->@* == 0)
    {
        push @unknownPatients, $data->{"PatientSerNum"};
        next;
    }

    $ormsToOacis->{$data->{'PatientSerNum'}} = {
        "oacisId" => $patient->{'internalId'},
        "mrn" => $data->{'PatientId'},
        "mrnType" => [map { $_->{"mrnType"} } $patient->{"mrns"}->@*]
    };
}

#convert the ADT and db patient data to json objects and store them in a file

write_json("./data/ormsToOacis.json",$ormsToOacis);
write_json("./data/unknown.json",\@unknownPatients);

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
    # @potentialPatients = grep { rMatch($ormsPatient,$_) } @potentialPatients;

    return undef if(!@potentialPatients or scalar @potentialPatients > 1);

    my $matchedPatient = $potentialPatients[0];

    #filter any non active mrns
    $matchedPatient->{'mrns'}->@* = grep { $_->{'active'} eq 'true' } $matchedPatient->{'mrns'}->@*;

    #convert mrn array to hash with 'MC_ADT','MR_PCS' or 'MG_PCS' keys
    # my %mrns = map { $_->{'mrnType'} => $_ } $matchedPatient->{'mrns'}->@*;
    # $matchedPatient->{'mrns'} = \%mrns;

    return $matchedPatient;
}

# Firstname, Lastname, MRN match
#Compares an Orms patient and an ADT entry by trying to match their first and last names, and an mrn
#Returns 1 on match, 0 otherwise
sub flmMatch
{
    my $ormsPatient = shift; #hash ref
    my $adtPatient = shift; #hash ref

    #strip spaces on all name strings
    $_ =~ s/\s+//g for($ormsPatient->{'FirstName'},$adtPatient->{'firstName'},$ormsPatient->{'LastName'},$adtPatient->{'lastName'});

    #compare first name
    return 0 if($ormsPatient->{'FirstName'} ne $adtPatient->{'firstName'});

    #compare last name
    if($ormsPatient->{'LastName'} ne $adtPatient->{'lastName'})
    {
        #if last names don't match, there's a possibility we're not looking at the right last name
        #for example, a patient marries and changes their last name
        #we can try using the 'otherName' field
        # return 0 if($ormsPatient->{'LastName'} ne $adtPatient->{'otherName'});

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

sub rMatch
{
    my $ormsPatient = shift; #hash ref
    my $adtPatient = shift; #hash ref

    #compare the ramq
    return 0 if($ormsPatient->{'SSN'} ne $adtPatient->{'ramqNumber'});

    return 1;
}