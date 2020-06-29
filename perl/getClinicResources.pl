#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all resources matching the specified speciality from the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use Time::Piece;
use v5.16;
use lib "./system/modules";

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;

use List::MoreUtils qw(uniq);

use LoadConfigs;

#-----------------------------------------
#start html feedback
#-----------------------------------------
my $cgi = CGI->new;
print $cgi->header('application/json');

#------------------------------------------
#parse input parameters
#------------------------------------------
my $speciality = param("speciality");

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#get the list of possible appointments and their resources
my $sql0 = "
	SELECT DISTINCT
		ClinicResources.ResourceName
	FROM
		ClinicResources
	WHERE
		ClinicResources.Speciality = ?
	ORDER BY
		ClinicResources.ResourceName";

my $query0 = $dbh->prepare_cached($sql0) or die("Query could not be prepared: ".$dbh->errstr);
$query0->execute($speciality) or die("Query execution failed: ".$query0->errstr);

my @resources;

while(my @data0 = $query0->fetchrow_array())
{
	my $resource = $data0[0];
	$resource =~ s/(\t|\r|\n)//g; #remove tabs and newlines
	push @resources, $resource;
}

my $sql1 = "
	SELECT DISTINCT
		MediVisitAppointmentList.AppointmentCode
	FROM
        MediVisitAppointmentList
        INNER JOIN ClinicResources ON ClinicResources.ResourceName = MediVisitAppointmentList.ResourceDescription
            AND ClinicResources.Speciality = ?";

my $query1 = $dbh->prepare_cached($sql1) or die("Query could not be prepared: ".$dbh->errstr);
$query1->execute($speciality) or die("Query execution failed: ".$query1->errstr);

my @appointments;

while(my @data1 = $query1->fetchrow_array())
{
	my $app = $data1[0];
	$app =~ s/(\t|\r|\n)//g; #remove tabs and newlines
	push @appointments, $app;
}


#remove duplicate entries
@resources = uniq @resources;
@appointments = uniq @appointments;

my $json = JSON->new->ascii->allow_nonref;

say $json->encode({"resources" => \@resources,"appointments" => \@appointments});

exit;
