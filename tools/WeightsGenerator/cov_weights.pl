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

#print CGI->new->header('application/json');
say "Content-Type: text/html\n";
#say '<meta charset="utf-8">';

#-----------------------------------------------------
#connect to database
#-----------------------------------------------------
my $dbh = DBI->connect_cached("DBI:mysql:database=WaitRoomManagement;host=localhost","readonly","readonly") or die("Couldn't connect to database: ".DBI->errstr);

my $sql = " 
	SELECT Patient.PatientId,Patient.PatientId_MGH,PatientMeasurement.*
	FROM PatientMeasurement
	 INNER JOIN Patient ON Patient.PatientSerNum = PatientMeasurement.PatientSer
	WHERE
		Patient.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC')
		AND Patient.PatientId NOT LIKE 'Opal%'
		AND PatientMeasurement.Height != 0";

my $query = $dbh->prepare($sql) or die("Query could not be prepared: ".$dbh->errstr);

$query->execute() or die("Query execution failed: ".$query->errstr);

my $patients = {};

while(my $data = $query->fetchrow_hashref())
{
	push @{$patients->{$data->{'PatientSer'}}}, $data;
}

my $stats = {};
my $covStats = {};

open(my $file,">","cov.txt");

foreach my $pat (keys %{$patients})
{
	my $average = 0;
	for(@{$patients->{$pat}})
	{
		$average += $_->{'Height'};
	}
	$average = $average / scalar @{$patients->{$pat}} if($average != 0);

	my $std = 0;
	for(@{$patients->{$pat}})
	{
		$std += ($_->{'Height'} - $average)**2;
	}
	$std = ($std / scalar @{$patients->{$pat}})**0.5 if($std != 0);

	my $cov = 100*$std/$average if($average != 0);
	$cov = sprintf("%.0f",$cov);

	$stats->{$patients->{$pat}->[0]->{'PatientSer'}} = $cov;
	$covStats->{$cov}++;
	say $file "$patients->{$pat}->[0]->{'PatientSer'}, $cov" if($cov > 20 && $cov < 30);
}

#say "$_, $covStats->{$_}<br>" for(keys %{$covStats});
#say "$covStats->{$_}<br>" for(keys %{$covStats});
#say "$_<br>" for(keys %{$covStats});


=begin
say scalar @jsonData;
my $json = JSON->new->allow_nonref;
my $JSON = $json->encode(\@jsonData);
say $JSON;
=cut

$dbh->disconnect;
exit;
