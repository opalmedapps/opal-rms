#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all the locations patients visited during the specified time range for the appointments in the specified time range and in the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

# bugs:
#  if a patient has 2 appointments and visits the same room in the morning and the afternoon, once for each appointment, the AM + PM counts > total counts for the day
# example:
#				|13:00|
#	|room A	(for app1)	|#################|room A
#	|room A			|#################|room A (for app2)
#
#	AM counts for app1: 1
#	PM counts for app2: 1
#	true counts for the day for app1: 1 < AM + PM = 2
#	true counts for the day for app2: 1 < AM + PM = 2
#

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use v5.26;

use lib '../../perl/system/modules';
use LoadConfigs;

use Time::Piece;
use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;

#-----------------------------------------
#start html feedback
#-----------------------------------------
my $cgi = CGI->new;
print $cgi->header('application/json');

#------------------------------------------
#parse input parameters
#------------------------------------------
my $sDateInit = param("sDate");
my $eDateInit = param("eDate");
my $period = param("period");

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

my $checkinCondition;

#check what period of the day the user specified and filter appointments that are not within that timeframe
if($period eq 'All')
{
	$checkinCondition = "AND CAST(PatientLocationMH.ArrivalDateTime AS TIME) BETWEEN '00:00:00' AND '23:59:59'";
}
elsif($period eq 'AM')
{
	$checkinCondition = "AND CAST(PatientLocationMH.ArrivalDateTime AS TIME) BETWEEN '00:00:00' AND '12:59:59'";
}
elsif($period eq 'PM')
{
	$checkinCondition = "AND CAST(PatientLocationMH.ArrivalDateTime AS TIME) BETWEEN '13:00:00' AND '23:59:59'";
}

#-----------------------------------------------------
#setup global variables
#-----------------------------------------------------
my %usageCounts; #indicates the number of times a room was used for an specific appointment

#structure:
#$usageCounts{$RoomName}{$AppName} -> counts

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get a list of all rooms that patients were checked into and for which appointment
my $sql = "
	SELECT DISTINCT
		Patient.PatientId,
		MediVisitAppointmentList.ResourceDescription,
		PatientLocationMH.CheckinVenueName,
		MediVisitAppointmentList.ScheduledDate
	FROM
		MediVisitAppointmentList MediVisitAppointmentList
		INNER JOIN Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
			AND Patient.PatientId NOT IN  ('9999996','9999997','9999998','999999997')
		INNER JOIN PatientLocationMH PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
			AND PatientLocationMH.CheckinVenueName NOT IN ('VISIT COMPLETE','ADDED ON BY RECEPTION','BACK FROM X-RAY/PHYSIO','SENT FOR X-RAY','SENT FOR PHYSIO','RC RECEPTION','OPAL PHONE APP')
			AND PatientLocationMH.CheckinVenueName NOT LIKE '%Ortho%'
            AND PatientLocationMH.CheckinVenueName NOT LIKE '%WAITING ROOM%'
			$checkinCondition
	WHERE
		MediVisitAppointmentList.ScheduledDateTime BETWEEN '$sDate' AND '$eDate'
		AND MediVisitAppointmentList.Status = 'Completed'
		AND MediVisitAppointmentList.ResourceDescription != 'Oncologie Traitement - Glen'
		AND MediVisitAppointmentList.ResourceDescription NOT LIKE '%blood%'";

my $query = $dbh->prepare_cached($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

#----------------------------------------
#process data
#----------------------------------------
while(my $data = $query->fetchrow_hashref)
{
	my %data = %{$data};

	$usageCounts{$data{'CheckinVenueName'}}{$data{'ResourceDescription'}}++;
}

#----------------------------------------
#output json
#----------------------------------------
#convert the hash data into a json string
my $json;

foreach my $room (sort keys %usageCounts)
{
	foreach my $appName (sort keys $usageCounts{$room}->%*)
	{
		$json .= "{\"Room\":\"$room\",\"Appointment\":\"$appName\",\"Counts\":\"$usageCounts{$room}{$appName}\"},";
	}
}

$json = substr($json,0,-1) if(substr($json,-1) eq ',');

say "[$json]";

exit;
