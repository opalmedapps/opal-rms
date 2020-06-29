#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script create a distribution of patient wait times in the specified date range for each doctor and clinic in the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use v5.16;

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
my $speciality = param("mode"); #either ortho or onc; indicates which category of appointments to find
my $method = param("method"); #normally blank, but if it is set to 'scheduled', the report will find the time difference from when the patient was called to their appointment scheduled time

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

#-----------------------------------------------------
#setup global variables
#-----------------------------------------------------
#the resource id and the appointment serial will be used as the keys
my %fname;
my %lname;
my %pID;
my %day; #day of the appointment
my %waitPeriod; #time when patient is checked in the waiting room
my %venue; #the room the patient is in
my %waitTime; #time the patient waiting in a waiting room
my %category; #for highcharts; tells us which time range an appointment falls in

#only uses the resource id
my %resDesc; #resource Description;
my %graphData; #array that stores waitTimes; used for highcharts

my $format = '%Y-%m-%d %H:%M:%S';

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get a list of patients who waited in a waiting room
my $sql = "
	SELECT DISTINCT
		MV.Resource,
		MV.ResourceDescription,
		MV.ScheduledDateTime,
		PL.AppointmentSerNum,
		Patient.FirstName,
		Patient.LastName,
		Patient.PatientId,
		PL.PatientLocationRevCount,
		CAST(PL.ArrivalDateTime AS DATE),
		PL.ArrivalDateTime,
		PL.DichargeThisLocationDateTime,
		PL.CheckinVenueName
	FROM
		PatientLocationMH PL,
		MediVisitAppointmentList MV,
		ClinicResources CR,
		Patient
	WHERE
		PL.AppointmentSerNum = MV.AppointmentSerNum
		AND MV.PatientSerNum = Patient.PatientSerNum
		AND MV.ClinicResourcesSerNum = CR.ClinicResourcesSerNum
		AND (PL.CheckinVenueName LIKE '%Waiting%'
			OR PL.CheckinVenueName LIKE '%WAITING%'
			-- OR PL.CheckinVenueName = 'ADDED ON BY RECEPTION'
			)
		AND PL.ArrivalDateTime >= '$sDate'
		AND PL.ArrivalDateTime <= '$eDate'
		AND Patient.LastName != 'ABCD'
		AND Patient.FirstName != 'Test Patient'
		AND Patient.LastName != 'Opal IGNORER SVP'
		AND CR.Speciality = '$speciality'
	ORDER BY Patient.LastName,Patient.FirstName,PL.AppointmentSerNum,PL.ArrivalDateTime";

my $query = $dbh->prepare_cached($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

#checkin and checkout should probably be renamed to better reflect the changes made regarding using the scheduled start time - 2018-07-09
while(my @data = $query->fetchrow_array())
{
	my $res = $data[0];
	$resDesc{$res} = $data[1];
	my $appStart = $data[2];
	my $ser = $data[3];

	$fname{$res}{$ser} = $data[4];
	$lname{$res}{$ser} = $data[5];
	$pID{$res}{$ser} = $data[6];
	$day{$res}{$ser} = $data[8];
	my $checkin = $data[9];
	my $checkout = $data[10];
	my $ven = $data[11];
	$ven =~ s/WAITING ROOM|Waiting Room//;
	#$ven =~ s/ADDED ON BY RECEPTION/Add On/;

	#record all venues a patient waited in
	$venue{$res}{$ser}{$ven} = 1;

	#if the method is set to 'scheduled', we instead calculate the time between when the patient was called (ie when they checkout of the waiting room) to their appointment scheduled start
	if($method eq 'scheduled')
	{
		$checkin = $appStart;
	}

	#convert datestimes to Time::Piece objects
	my $checkinTP = Time::Piece->strptime($checkin,$format);
	my $checkoutTP = Time::Piece->strptime($checkout,$format);

	#add the wait to the total waitime
	$waitTime{$res}{$ser} += $checkoutTP - $checkinTP;

	$checkin = substr($checkin,11);
	$checkout = substr($checkout,11);

	#if the patient has been waiting too long, it is likely that the patient was never checked out.
	#usually, this is noticed a few days after so the checkout date is not valid
	$checkout = "N/A" if($waitTime{$res}{$ser} > 15* Time::Piece->ONE_HOUR);
	$checkout = "N/A" if($waitTime{$res}{$ser} < -15* Time::Piece->ONE_HOUR);

	#we set the patient's wait time to the maximum so that they appear on the highcharts chart
	if($waitTime{$res}{$ser} > 8* Time::Piece->ONE_HOUR) {$waitTime{$res}{$ser} = 8.1* Time::Piece->ONE_HOUR;}
	if($waitTime{$res}{$ser} < -8* Time::Piece->ONE_HOUR) {$waitTime{$res}{$ser} = -8.1* Time::Piece->ONE_HOUR;}

	$waitPeriod{$res}{$ser} .= "$checkin - $checkout, ";

}

my $jstring = "{";

foreach my $res (keys %resDesc)
{
	#concatenate waitimes and rooms
	foreach my $ser (keys $venue{$res}->%*)
	{
		chop($waitPeriod{$res}{$ser},$waitPeriod{$res}{$ser});

		my $venues = "";
		for (keys $venue{$res}{$ser}->%*) {$venues .= "$_, ";}
		chop($venues,$venues);
		$venue{$res}{$ser} = $venues;
	}

	#store patient data in json format
	$jstring .= "\"$res\":{\"name\":\"$resDesc{$res}\",\"patientData\":[";
	for (sort{$a <=> $b} keys $waitTime{$res}->%*)
	{
		$jstring .= "{\"fname\":\"$fname{$res}{$_}\",\"lname\":\"$lname{$res}{$_}\",\"pID\":\"$pID{$res}{$_}\",\"room\":\"$venue{$res}{$_}\",\"day\":\"$day{$res}{$_}\",\"waitPeriod\":\"$waitPeriod{$res}{$_}\",\"waitTime\":\"$waitTime{$res}{$_}\"},";

		my $int = findInterval($waitTime{$res}{$_});
		$graphData{$res}{$int}++;
	}
	chop($jstring);
	$jstring .= "],\"graphData\":[";

	#store highcharts data in json
	for $_ (sort{$a <=> $b} keys $graphData{$res}->%*)
	{
		$graphData{$res}{$_} += 0;
		$jstring .= "[$_,$graphData{$res}{$_}],";
	}
	chop($jstring);
	$jstring .= "]},";
}

chop($jstring) if($jstring ne "{");
$jstring .= "}";

say $jstring;

#calculates which wait time category a patient belongs to
sub findInterval
{
	my $val = $_[0];
	my $interval = 0.5 * int($val / (30* Time::Piece->ONE_MINUTE));

	$interval -= 0.5 if($interval <= 0 and $val < 0);

	return $interval;
}

exit;
