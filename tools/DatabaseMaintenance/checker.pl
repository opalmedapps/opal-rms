#!/usr/bin/perl

#-----------------------------------------------
#
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
use warnings;
use v5.16;

use File::JSON::Slurper qw(read_json write_json);

use Data::Dumper;

#read the list of patient serials
my @perfect = read_json('perfectMatch.json')->@*;
my @partial = read_json('partialMatch.json')->@*;
my @potential = read_json('potentialMatch.json')->@*;
my @existing = read_json('existingMatch.json')->@*;
my @none = read_json('noMatch.json')->@*;

my @matchedWeights = read_json('matchedWeights.json')->@*;
my @unmatchedWeights = read_json('unmatchedWeights.json')->@*;

say scalar @perfect;
say scalar @partial;
say scalar @potential;
say scalar @existing;
say scalar @none;

say scalar @matchedWeights;
say scalar @unmatchedWeights;


#to fix appoimtment IDs in orms db

#if we have full encounterID for appointment -> replace appointId with appointIdIn

#if we have 8 digit appointId and no AppointIdIn -> replace appointIdIn with appointId -> rerun analysis

#if appointId has 3 digits -> replace appointId with appointIdIn -> rerun analysis

#if appointId has 'PreIE' -> match with a patient using Resource Code and Patient Id from appointment list

#if 8 digit appointIdIn -> can be visit or appointment -> match with seq RV or visit Id and date/time

#KENTEREDX appointId -> match date/resource with appointment