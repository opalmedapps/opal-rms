#!/usr/bin/perl

#-----------------------------------------------
# Script that updates the orms db with the proper first and last names from the hospital ADT
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
#use warnings;
use v5.26;

use Cwd qw(abs_path);

#my $runningDir;
#BEGIN {
#	$runningDir = abs_path(__FILE__ ."/../");
#}

#use lib "$runningDir/../../perl/system/modules/";
#use LoadConfigs;
use File::JSON::Slurper qw(read_json write_json);
use Text::CSV qw(csv);
use List::MoreUtils qw(uniq);
use Data::Dumper;

#get the list of appointments we're sure belong to the patient
my @verified = read_json('perfectMatch.json')->@*;
my @partiallyVerified = read_json('partialMatch.json')->@*;

push @verified, @partiallyVerified;

my $verifiedAppointments = {};
$verifiedAppointments->{$_} = 1 for (@verified);

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
#my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#custom connection
use DBI;
my $dbh = DBI->connect_cached("DBI:MariaDB:database=WaitRoomManagement;host=172.26.66.41",'readonly','readonly') or die("Can't connect");

#get a list of all the patients in the database
my $sqlWeightList = "
	SELECT
		PatientMeasurement.PatientMeasurementSer,
		PatientMeasurement.Date,
		MV.AppointmentSerNum,
		MV.AppointIdIn
	FROM
		PatientMeasurement
		INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = PatientMeasurement.PatientSer
			AND MV.PatientSerNum NOT IN (33651,52641,827,27183,21265,35870,845,44281,44282,44284,44287,44529)
			-- AND MV.AppointId NOT LIKE '%Pre%'
			-- AND MV.AppointIdIn != 'InstantAddOn'
			-- AND MV.Status != 'Deleted'
	WHERE
		PatientMeasurement.Date = MV.ScheduledDate
	-- LIMIT 1
	";

my $queryWeightList = $dbh->prepare($sqlWeightList) or die("Couldn't prepare statement: ". $dbh->errstr);
$queryWeightList->execute() or die("Couldn't execute statement: ". $queryWeightList->errstr);

my @matched;
my @unmatched;

#for each weight, check whether the patient actually had an appointment that day
while(my $weight = $queryWeightList->fetchrow_hashref())
{
	if($verifiedAppointments->{$weight->{'AppointmentSerNum'}} || $weight->{'AppointIdIn'} eq 'InstantAddOn')
	{
		push @matched, $weight->{'PatientMeasurementSer'};
	}
	else
	{
		push @unmatched, $weight->{'PatientMeasurementSer'};
	}
}

@matched = uniq @matched;
@unmatched = uniq @unmatched;

write_json("matchedWeights.json",\@matched);
write_json("unmatchedWeights.json",\@unmatched);


exit;


# my $sql = "SELECT PM.* FROM WaitRoomManagement20190909_weights_backup.PatientMeasurement PM WHERE PM.PatientMeasurementSer NOT IN (SELECT PM2.PatientMeasurementSer FROM WaitRoomManagement.PatientMeasurement PM2)";

# my $query = $dbh->prepare($sql) or die("Couldn't prepare statement: ". $dbh->errstr);
# $query->execute() or die("Couldn't execute statement: ". $query->errstr);

# #for each weight, check whether the patient actually had an appointment that day
# while(my $weight = $query->fetchrow_hashref())
# {
# 	$dbh->do("
# 		INSERT INTO WaitRoomManagement.PatientMeasurement(PatientMeasurementSer,PatientSer,Date,Time,Height,Weight,BSA,LastUpdated)
# 		VALUES (?,?,?,?,?,?,?,?)
# 		",undef,
# 		$weight->{'PatientMeasurementSer'},
# 		$weight->{'PatientSer'},
# 		$weight->{'Date'},
# 		$weight->{'Time'},
# 		$weight->{'Height'},
# 		$weight->{'Weight'},
# 		$weight->{'BSA'},
# 		$weight->{'LastUpdated'}
# 		);
# }
