#!/usr/bin/perl

#-----------------------------------------------
# Script that filters all patients whose primary mrn is not RVH
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
#use warnings;
use experimental "smartmatch";
use v5.26;

use File::JSON::Slurper qw(read_json write_json);

use Data::Dumper;

my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request

#read input data and data from previous run
my $ormsToOacis = read_json("./data/ormsToOacis.json");

#loop over all patients and figure out which ones have rvh mrns
my $count = 0;
my $rvhPatients = {};

foreach my $ormsSer (keys $ormsToOacis->%*)
{
    $count++;
    say $count if($count % 100 == 0);

    next unless( "MR_PCS" ~~ $ormsToOacis->{$ormsSer}->{"mrnType"});

    $rvhPatients->{$ormsSer} = $ormsToOacis->{$ormsSer};
}

write_json("./data/rvhPatients.json",$rvhPatients);

exit;
