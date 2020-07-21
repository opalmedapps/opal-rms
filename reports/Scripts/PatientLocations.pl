#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all the locations patients visited during the specified time range for the appointments in the specified time range and in the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

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
my $clinic = param("clinic");
my $specifiedAppointment = param("filter");

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

my $specialityFilter = "AND ClinicResources.Speciality = '$clinic' ";

my $appointmentFilter = "";
$appointmentFilter = " AND MediVisitAppointmentList.AppointmentCode LIKE '%$specifiedAppointment%' " if($specifiedAppointment);

#-----------------------------------------------------
#setup global variables
#-----------------------------------------------------
#the patient serial will be used as the key
my %fname;
my %lname;
my %pID;

#the appointment date and the appointment serial will be used as the keys
my %venue; #the room the patient is in
my %start; #the time the patient entered the venue
my %end; #the time the patient left the venue
my %waitTime; #time spent inside the venue
my %appointment; #name of the appointment the patient is scheduled for
my %appCode; #appointment code
my %appStatus; #appointment status
my %appTime; #scheduled time of the appointment
my %rooms; #integer associated with a specific room
my %chartData; #data needed for highcharts

my %properApp; #"proper appointment"; indicates if the appointment has (any) associated check ins for it

my $format = '%Y-%m-%d %H:%M:%S';

my %roomHash = createRoomHash();

my $jstring = "{";

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get a list of check in/outs for patients who had an appointment in the specified date range
#this includes the PatientLocation table
my $sql = "
	SELECT DISTINCT
		UN.ScheduledDate,
		UN.AppointmentSerNum,
		UN.PatientSerNum,
		UN.FirstName,
		UN.LastName,
		UN.PatientId,
		UN.CheckinVenueName,
		UN.ArrivalDateTime,
		UN.DichargeThisLocationDateTime,
		UN.ResourceDescription,
		UN.AppointmentCode,
		UN.Status,
		UN.ScheduledTime,
		UN.PatientLocationRevCount
	FROM (
		SELECT DISTINCT
			MediVisitAppointmentList.ScheduledDate,
			MediVisitAppointmentList.AppointmentSerNum,
			Patient.PatientSerNum,
			Patient.FirstName,
			Patient.LastName,
			Patient.PatientId,
			PatientLocationMH.CheckinVenueName,
			PatientLocationMH.ArrivalDateTime,
			PatientLocationMH.DichargeThisLocationDateTime,
			PatientLocationMH.PatientLocationRevCount,
			MediVisitAppointmentList.ResourceDescription,
			MediVisitAppointmentList.AppointmentCode,
			MediVisitAppointmentList.Status,
			MediVisitAppointmentList.ScheduledTime
		FROM
			Patient
			INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
				AND MediVisitAppointmentList.Status != 'Deleted'
				AND MediVisitAppointmentList.ScheduledDate BETWEEN '$sDate' AND '$eDate'
				$appointmentFilter
			LEFT JOIN PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
			INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
				$specialityFilter
		UNION
		SELECT DISTINCT
			MediVisitAppointmentList.ScheduledDate,
			MediVisitAppointmentList.AppointmentSerNum,
			Patient.PatientSerNum,
			Patient.FirstName,
			Patient.LastName,
			Patient.PatientId,
			PatientLocation.CheckinVenueName,
			PatientLocation.ArrivalDateTime,
			'1970' AS DichargeThisLocationDateTime,
			PatientLocation.PatientLocationRevCount,
			MediVisitAppointmentList.ResourceDescription,
			MediVisitAppointmentList.AppointmentCode,
			MediVisitAppointmentList.Status,
			MediVisitAppointmentList.ScheduledTime
		FROM
			Patient
			INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
				AND MediVisitAppointmentList.Status != 'Deleted'
				AND MediVisitAppointmentList.ScheduledDate BETWEEN '$sDate' AND '$eDate'
				$appointmentFilter
			LEFT JOIN PatientLocation ON PatientLocation.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
			INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
				$specialityFilter
		) AS UN
	WHERE UN.PatientId != '9999996'
	ORDER BY UN.ScheduledDate,UN.PatientId,UN.AppointmentSerNum,UN.PatientLocationRevCount";


my $query = $dbh->prepare_cached($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

#store the return row data from the execute in another array because we'll need to use it twice
my @rows = ();
while(my @row = $query->fetchrow_array())
{
	push @rows, \@row;
}

#due to the LEFT JOIN in the query, we get rows which have no check in time/venue
#we need to distinguish between appointments which have empty columns due to the LEFT JOIN and appointments that have no checkin associated with them
foreach my $row (@rows)
{
	my @data = @{$row};
	my $ser = $data[1];
	$properApp{$ser} = 1 if($data[13]); #row[12] = PatientLocationRevCount
}

my $currentDate;
my $currentSer; #indicates the current patient we're on in the list of patients (the query result is ordered by date and patient, so the moment any of these change, we complete the current json object and create the next
foreach my $row (@rows)
{
	my @data = @{$row};

	my $date = $data[0];
	my $ser = $data[1];

	$fname{$ser} = $data[3];
	$lname{$ser} = $data[4];
	$pID{$ser} = $data[5];

	$appointment{$date}{$ser} = $data[9];
	$appCode{$date}{$ser} = $data[10];
	$appStatus{$date}{$ser} = $data[11];
	$appTime{$date}{$ser} = $data[12];

	my $ven = $data[6]; #the venue
	my $st = $data[7]; #check in time
	my $ed = $data[8]; #check out time

	#if the  appointment has at least one PatientLocation/PatientLocationMH row associated with it, then we can determine the check in time and other information
	#if not, we just mark the relevant arrays as empty
	if($properApp{$ser} and $st)
	{
		#sometimes the venue is null (a bug) so in that case rename the room
		if($ven eq '') {$ven = "The Blank Room";}

		push @{$venue{$date}{$ser}}, $ven;

		#store the numerical representation of the venue
		#or -1 if it isn't found
		if($roomHash{$ven}) {push @{$rooms{$date}{$ser}}, $roomHash{$ven};}
		else {push @{$rooms{$date}{$ser}}, -1;}

		push @{$start{$date}{$ser}}, $st;

		#the 1970 was assigned in the query
		if($ed eq '1970') {$ed = "Not Checked Out";}
		push @{$end{$date}{$ser}}, $ed;

		my $stTP = Time::Piece->strptime($st,$format);
		my $edTP = Time::Piece->strptime($ed,$format) if($ed ne "Not Checked Out");

		my $hours;
		my $color;

		if($ed eq "Not Checked Out")
		{
			push @{$waitTime{$date}{$ser}}, '';

			$hours = sprintf('%0.2f',(localtime() + (localtime())->tzoffset - $stTP)/3600);
			$color = assignColor($ven);
			$ven = "Current: $ven";
		}
		else
		{
			push @{$waitTime{$date}{$ser}}, sprintf('%0.1f',($edTP - $stTP)/3600);

			$hours = sprintf('%0.2f',($edTP - $stTP)/3600);
			$color = assignColor($ven);
		}

		if(!$chartData{$date}{$ser} or scalar @{$chartData{$date}{$ser}} eq 0)
		{
			my $notCheckedIn = sprintf('%0.2f',($stTP - Time::Piece->strptime($date,'%Y-%m-%d'))/3600);

			push @{$chartData{$date}{$ser}}, "[$notCheckedIn,\"transparent\",\"Not Checked In\"]";
		}

		#hours determines the width the venue will take in the highcharts chart
		#if it is too small, we won't be able to see it
		if($hours < 0.02) {$hours = 0.02;}
		push @{$chartData{$date}{$ser}}, "[$hours,\"$color\",\"$ven\"]";
	}
	else
	{
		if(!$venue{$date}{$ser})
		{
			@{$venue{$date}{$ser}} = ();
			@{$rooms{$date}{$ser}} = ();
			@{$start{$date}{$ser}} = ();
			@{$end{$date}{$ser}} = ();
			@{$chartData{$date}{$ser}} = ();
		}
	}
}
my $lastChar;

foreach my $date (sort{$a cmp $b} keys %appointment)
{
	$jstring .= "\"$date\":[";
	foreach my $ser (sort{$pID{$a} cmp $pID{$b}} keys %{$venue{$date}})
	{
		$jstring .= "{\"fname\":\"$fname{$ser}\",\"lname\":\"$lname{$ser}\",\"pID\":\"$pID{$ser}\",\"app\":\"$appointment{$date}{$ser}\",\"code\":\"$appCode{$date}{$ser}\",\"status\":\"$appStatus{$date}{$ser}\",\"time\":\"$appTime{$date}{$ser}\",\"rooms\":[";
		$jstring .= "$_," for @{$rooms{$date}{$ser}};

		$lastChar = chop($jstring);
		if($lastChar eq ',') {$jstring .= "],";}
		else {$jstring .= "[],";}

		$jstring .= "\"data\":[";
		$jstring .= "[\"@{$venue{$date}{$ser}}[$_]\",\"@{$start{$date}{$ser}}[$_]\",\"@{$end{$date}{$ser}}[$_]\",\"@{$waitTime{$date}{$ser}}[$_]\"]," for (0..(scalar @{$venue{$date}{$ser}}-1));

		$lastChar = chop($jstring);
		if($lastChar eq ',') {$jstring .= "],";}
		else {$jstring .= "[],";}

		$jstring .= "\"chartData\":[";
		$jstring .= "@{$chartData{$date}{$ser}}[$_]," for (0..(scalar @{$chartData{$date}{$ser}}-1));

		$lastChar = chop($jstring);
		if($lastChar eq ',') {$jstring .= "]},";}
		else {$jstring .= "[]},";}
	}

	$lastChar = chop($jstring);
	if($lastChar eq ',') {$jstring .= "],";}
	else {$jstring .= "[],";}
}

$lastChar = chop($jstring);
if($lastChar eq ',') {$jstring .= "}";}
else {$jstring .= "{}";}

say $jstring;

exit;

#function that initializes a hash with the possible rooms and assigns a number to each room so that angular can later know which room column it should fill
sub createRoomHash
{
	my %roomHash = (
		"The Blank Room" => 1,
		"8225" => 2,
		"A1 EXAM ROOM" => 3,
		"A1 EXAM ROOM." => 4,
		"A2 EXAM ROOM" => 5,
		"A3 EXAM ROOM" => 6,
		"A4 EXAM ROOM" => 7,
		"A5 EXAM ROOM" => 8,
		"A5 EXAM ROOM -a" => 9,
		"A6 EXAM ROOM" => 10,
		"ADDED ON BY RECEPTION" => 11,
		"B1 EXAM ROOM" => 12,
		"B2 EXAM ROOM" => 13,
		"B3 EXAM ROOM" => 14,
		"B4 EXAM ROOM" => 15,
		"B5 EXAM ROOM" => 16,
		"B6 EXAM ROOM" => 17,
		"B7 EXAM ROOM" => 18,
		"B8 EXAM ROOM" => 19,
		"B9 EXAM ROOM" => 20,
		"BACK FROM X-RAY" => 21,
		"BACK FROM X-RAY/PHYSIO" => 22,
		"C1 EXAM ROOM" => 23,
		"C2 EXAM ROOM" => 24,
		"C3 EXAM ROOM" => 25,
		"C4 EXAM ROOM" => 26,
		"C5 EXAM ROOM" => 27,
		"C6 EXAM ROOM" => 28,
		"C7 EXAM ROOM" => 29,
		"C8 EXAM ROOM" => 30,
		"CHEMO COMPLETE" => 31,
		"D1 EXAM ROOM" => 32,
		"D2 EXAM ROOM" => 33,
		"D3 EXAM ROOM" => 34,
		"D6 EXAM ROOM" => 35,
		"D7 EXAM ROOM" => 36,
		"D8 EXAM ROOM" => 37,
		"D9 EXAM ROOM" => 38,
		"DRC Waiting Room" => 39,
		"DS1 Waiting Room" => 40,
		"Midnight" => 41,
		"OPAL PHONE APP" => 42,
		"Ortho Cast Room 1" => 43,
		"Ortho Cast Room 2" => 44,
		"Ortho Cast Room 3" => 45,
		"Ortho Cast Room 4" => 46,
		"Ortho Cast Room 5" => 47,
		"Ortho Generic" => 48,
		"Ortho Reception" => 49,
		"Ortho Room A1" => 50,
		"Ortho Room A2" => 51,
		"Ortho Room A3" => 52,
		"Ortho Room A4" => 53,
		"Ortho Room B5" => 54,
		"Ortho Room B6" => 55,
		"Ortho Room C7" => 56,
		"Ortho Room C8" => 57,
		"Ortho Room D10" => 58,
		"Ortho Room D11" => 59,
		"Ortho Room D12" => 60,
		"Ortho Room D9" => 61,
		"Ortho Treatment Room" => 62,
		"Ortho Waiting Room" => 63,
		"RC RECEPTION" => 64,
		"RC Waiting Room" => 65,
		"RT TX ROOM 1" => 66,
		"RT TX ROOM 3" => 67,
		"RT TX ROOM 6" => 68,
		"S1 RECEPTION" => 69,
		"S1 Waiting Room" => 70,
		"SENT FOR PHYSIO" => 71,
		"SENT FOR X-RAY" => 72,
		"SS1 EXAM ROOM" => 73,
		"TEST CENTRE WAITING ROOM" => 74,
		"Testing" => 75,
		"TX AREA A" => 76,
		"TX AREA B" => 77,
		"TX AREA C" => 78,
		"TX AREA D" => 79,
		"TX AREA E" => 80,
		"TX AREA F" => 81,
		"TX AREA G" => 82,
		"TX AREA H" => 83,
		"TX AREA U" => 84,
		"unknown" => 85,
		"VISIT COMPLETE" => 86);

	return %roomHash;
}

sub assignColor
{
	my $room = $_[0];
	my $color;

	if($room =~ /EXAM ROOM/) {$color = '#ff0000';}
	elsif($room =~ /Cast Room/) {$color = '#0099ff';}
	elsif($room =~ /Ortho Waiting Room/) {$color = '#99d6ff';}
	elsif($room =~ /Ortho Room/) {$color = '#0066ff';}
	elsif($room =~ /TX AREA/) {$color = '#ffff00';}
	elsif($room =~ /(RC Waiting Room|RC WAITING ROOM)/) {$color = '#66ff33';}
	elsif($room =~ /S1 Waiting Room/) {$color = '#cc99ff';}
	elsif($room =~ /SENT FOR X-RAY/) {$color = '#996633';}
	elsif($room =~ /SENT FOR PHYSIO/) {$color = '#ff9933';}
	elsif($room =~ /TEST CENTRE WAITING ROOM/) {$color = '#cc00ff';}
	elsif($room =~ /VISIT COMPLETE/) {$color = '#000001';}
	elsif($room =~ /The Blank Room/) {$color = '#ffffff';}
	elsif($room =~ /BACK FROM X-RAY\/PHYSIO/) {$color = '#ff33cc';}
	elsif($room =~ /Ortho Treatment Room/) {$color = '#000099';}
	else {$color = '#e6e6e6';}

	return $color;
}
