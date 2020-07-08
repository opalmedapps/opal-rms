#!/usr/bin/perl
use strict;
use lib "/var/www/devDocuments/MedPhysReports/Resources/Perl/";
use v5.14;
use Array::Utils qw(intersect array_diff);
use List::MoreUtils qw(uniq);
use List::Util;
use Time::Piece;
use Switch;
use Data::Dumper;
#use warnings;
#use diagnostics;

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;
use Try::Tiny;

#say "Content-type: text/html\n";

my $format = "%b %d %Y %I:%M%p";

use DBI;
#use DBD::Sybase;
#use Date::Calc;
#use Date::Calc qw(Decode_Month Today Now Decode_Date_US Today_and_Now Delta_DHMS);

use UtilityFetch;
#use LinkAppointmentAndPlan;
use File::Basename qw(fileparse);
use MIME::Base64 qw(encode_base64);

use MIME::Lite;

#use lib './Useful Scripts';
#use HospitalADT;

use Spreadsheet::Read;
use Encode;
use Encode::Guess;

print CGI->new->header('application/json');
#say "Content-Type: text/html\n";
#say '<meta charset="utf-8">';

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
my $dbh = DBI->connect_cached("DBI:mysql:database=MedPhysLog;host=localhost","readonly","readonly") or die("Couldn't connect to database: ".DBI->errstr);

=begin
my $sql = "
	SELECT
		Message,
		LastUpdated
	FROM
		VirtualWaitingRoomLog
	WHERE
		FileName = 'createWeightDocument.pl'
		AND Identifier = 'send_xml'
		AND LastUpdated BETWEEN '2019-07-10' AND '2019-07-24 23:59:00'
	ORDER BY LastUpdated DESC";

my $query = $dbh->prepare($sql) or die("Query could not be prepared: ".$dbh->errstr);

$query->execute() or die("query execution failed: ".$query->errstr);

my %hash = ();
while(my $data = $query->fetchrow_hashref())
{
	my $patId = $data->{'Message'};
	$patId =~ s/Sent weight xml for patient//;
	$patId =~ s/to server with message.+//;
	$patId =~ s/\D//g;

	my $date = $data->{'LastUpdated'};
	$date = substr($date,0,10);

	if($patId eq '575702') {$patId = '0575702';}
	if($patId eq '829616') {$patId = '0829616';}
	if($patId eq '0699063') {next;}

	$hash{$patId} = $date if(!$hash{$patId});
}
=cut

=begin
my @arr = keys %hash;
my $patList = join(",",@arr);

my $sql2 = "
	SELECT DISTINCT
		Patient.PatientId,
		ScheduledDate,
		Status
	FROM
		WaitRoomManagement.MediVisitAppointmentList
		INNER JOIN WaitRoomManagement.Patient ON Patient.PatientSerNum = MediVisitAppointmentList.PatientSerNum
			AND Patient.PatientId IN ($patList)
		INNER JOIN WaitRoomManagement.PatientLocationMH ON PatientLocationMH.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
	WHERE
		ScheduledDate BETWEEN '2019-07-25' AND '2019-07-27'
		AND Status != 'Cancelled'
		AND Status != 'Deleted'";



my $query2 = $dbh->prepare($sql2) or die("Query could not be prepared: ".$dbh->errstr);

$query2->execute() or die("Query execution failed: ".$query2->errstr);

my %secHash = ();

while(my $data = $query2->fetchrow_hashref())
{
	my $pID = $data->{'PatientId'};
	$pID =~ s/\D//g;

	#my $fDate = Time::Piece->strptime($hash{$pID},"%Y-%m-%d");
	#my $currentDate = Time::Piece->strptime($data->{'ScheduledDate'},"%Y-%m-%d");

	#if($fDate == $currentDate)
	#{
	#	next;
	#}
	#else
	#{
		$secHash{$pID} = $data->{'ScheduledDate'} if(!$secHash{$pID});
	#}
}

my @secArr = keys %secHash;
my $secPatList = join(",",@secArr);
=cut

my $sql3 = "
	SELECT DISTINCT
		Patient.LastName,
		Patient.FirstName,
		Patient.PatientId AS PatientIdRVH,
		Patient.PatientId_MGH AS PatientIdMGH,
		SUBSTRING(Patient.SSN,1,3) AS SSN,
		PatientMeasurement.Weight,
		PatientMeasurement.Height,
		PatientMeasurement.BSA
	FROM
		WaitRoomManagement.Patient
		INNER JOIN WaitRoomManagement.PatientMeasurement ON PatientMeasurement.PatientMeasurementSer =
			(
				SELECT
					PM.PatientMeasurementSer
				FROM
					WaitRoomManagement.PatientMeasurement PM
				WHERE
					PM.PatientSer = Patient.PatientSerNum
					-- AND PM.Date BETWEEN DATE_SUB(CURDATE(), INTERVAL 21 DAY) AND NOW()
				ORDER BY
					PM.Date DESC,
					PM.LastUpdated DESC
				LIMIT 1
			)
	WHERE
		Patient.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC')
		AND Patient.PatientId NOT LIKE 'Opal%'
		-- AND Patient.PatientId = '2294406'
		-- Patient.PatientId IN (patList)
		-- Patient.PatientId = '699063'
		-- Patient.PatientId IN ('5479291','1079748','1100527','2265224')
	ORDER BY Patient.PatientId";

my $query3 = $dbh->prepare($sql3) or die("Query could not be prepared: ".$dbh->errstr);

$query3->execute() or die("Query execution failed: ".$query3->errstr);

my @jsonData = ();

while(my $data = $query3->fetchrow_hashref())
{
	my $patObj = {
		LastName=> $data->{'LastName'},
		FirstName=> $data->{'FirstName'},
		PatientIdRVH=> "'$data->{'PatientIdRVH'}'",
		PatientIdMGH=> "'$data->{'PatientIdMGH'}'",
		SSN=> $data->{'SSN'},
		Weight=> $data->{'Weight'},
		Height=> $data->{'Height'},
		BSA=> $data->{'BSA'}
	};

	#system("perl ./createWeightDocument.pl $patObj->{'PatientIdRVH'} $patObj->{'PatientIdMGH'}");
	#say $patObj->{'PatientIdRVH'};

	push @jsonData, $patObj;
}

#verify that all files have been created
#my @files = glob("./docs/*.pdf");
=begin
foreach my $pat (@jsonData)
{
	my $id = $pat->{'PatientIdRVH'};
	$id =~ s/'//g;

	(my $noZeroesId = $id) =~ s/_$//;
	($noZeroesId = $noZeroesId) =~ s/0*(\d+)/$1/; #remove all leading zeroes in the patient id

	#if(-e "./weightGenerator/docs/$id\_FMU-4183.pdf" and -e "./weightGenerator/docs/MUHC-RV-$noZeroesId-FMU-4183^Aria.xml")
	if(-e "/var/www/devDocuments/weightGenerator/docs/$id\_FMU-4183.pdf" and -e "/var/www/devDocuments/weightGenerator/docs/MUHC-RV-$noZeroesId-FMU-4183^Aria.xml")
	{
		say "ok";
	}
	else
	{
		say "error: $id | $noZeroesId";
	}
}

say scalar @jsonData;
=cut
my $json = JSON->new->allow_nonref;

my $JSON = $json->encode(\@jsonData);

say $JSON;

$dbh->disconnect;
exit;
