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
		MV.AppointmentSerNum
	FROM
		PatientMeasurement		
		INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = PatientMeasurement.PatientSer
			AND MV.PatientSerNum != 0
			AND MV.PatientSerNum NOT IN (33651,52641,827,27183,21265,35870,845,44281,44282,44284,44287,44529)
			AND MV.AppointId NOT LIKE '%Pre%'
			AND MV.AppointIdIn != 'InstantAddOn'
			AND MV.Status != 'Deleted'
			-- AND MV.ScheduledDate < '2019-09-02'
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
	if($verifiedAppointments->{$weight->{'AppointmentSerNum'}})
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