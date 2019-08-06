#!/usr/bin/perl
#------------------------------------------------------------------------
# J.Kildea 
#------------------------------------------------------------------------
# Perl script to schedule hanging clinics in ORMS
# User should first examine the list of Hanging clinics in the WaitRoomManagement DB
# and assign a speciality to each
# Then run this script to assign each hanging clinic to its default exam room and schedule
# 
# Input parameters: 	various	
#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
use strict;
use v5.10;

#------------------------------------------------------------------------
# Load Modules
#------------------------------------------------------------------------
use Cwd qw(abs_path);

my $runningDir;
BEGIN {
	$runningDir = abs_path(__FILE__ ."/../");
}

use lib "$runningDir/../perl/";
use LoadConfigs;

#------------------------------------------------------------------------
# Important variables
#------------------------------------------------------------------------
my $help;
my $database;

#------------------------------------------------------------------------
# Read in the command line arguments
#------------------------------------------------------------------------
use Getopt::Long;
&GetOptions("h"	     => \$help,
	    "help"   => \$help 
	   );

#------------------------------------------------------------------------
# Process the command line input
# if there are no command line arguments then say so and die!
#------------------------------------------------------------------------

if ($help)
{
die "ScheduleHangingClinics\n

Usage:
	ScheduleHangingClinics

	database can be WaitRoomManagement or WaitRoomManagementDev

	Note: be sure to assign a speciality to each hanging clinic in the WaitRoomManagement 
 	database before proceeding...

		\n";
}

#------------------------------------------------------------------------
# Connect to the MUHC MySQL database 
#------------------------------------------------------------------------
my $dbh_mysql = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#------------------------------------------------------------------------
# Get the hanging clinics
#------------------------------------------------------------------------
my $HangingClinicsSQL = "
	SELECT
		MediVisitResourceDes, 
		Speciality	
	FROM
		HangingClinics	
	WHERE
		Speciality IS NOT NULL
";
# print "SQL: $HangingClinicsSQL\n";

# prepare query
my $query= $dbh_mysql->prepare($HangingClinicsSQL)
  or die "Couldn't prepare statement: " . $dbh_mysql->errstr;

# submit query
$query->execute()
  or die "Couldn't execute statement: " . $query->errstr;

my $MediVisitResourceDes;
my $numHangingClinics = 0;
my $Speciality = 0;
while(my @data = $query->fetchrow_array())
{
  #print "Data: @data<br>" if $verbose;

  $MediVisitResourceDes = $data[0]; 
  $Speciality		= $data[1]; 
  print "Found hanging clinic: $MediVisitResourceDes [$Speciality]\n";

  #------------------------------------------------------------------------
  # Get the default clinic/room for this resource
  #------------------------------------------------------------------------
  my $DefaultSQL = "
	SELECT
		ClinicScheduleSerNum	
	FROM
		ClinicSchedule	
	WHERE
		ClinicName LIKE \"%$Speciality Default%\"
  ";
  #print "SQL: $DefaultSQL\n";

  # prepare query
  my $query= $dbh_mysql->prepare($DefaultSQL)
    or die "Couldn't prepare statement: " . $dbh_mysql->errstr;

  # submit query
  $query->execute()
    or die "Couldn't execute statement: " . $query->errstr;

  # retrieve the PatientSer, assuming it exists
  my $ClinicScheduleSerNum = $query->fetchrow_array();

  #------------------------------------------------------------------------
  # Insert this hanging clinic into the schedule using the default for
  # its speciality
  #------------------------------------------------------------------------
  # prepare SQL
  my $InsertHangingClinicSQL = "INSERT INTO ClinicResources (ResourceName,Speciality,ClinicScheduleSerNum) VALUES (\"$MediVisitResourceDes\",\"$Speciality\", $ClinicScheduleSerNum)"; 

  #print "SQL: $InsertHangingClinicSQL\n";


  # prepare query
  $query= $dbh_mysql->prepare($InsertHangingClinicSQL)
    or die "Couldn't prepare statement: " . $dbh_mysql->errstr;

  # submit query
  $query->execute()
    or die "Couldn't execute statement: " . $query->errstr;

  #------------------------------------------------------------------------
  # Delete the now scheduled hanging clinic from the hanging clinics table
  #------------------------------------------------------------------------
  # prepare SQL
  my $DeleteHangingClinicSQL = "DELETE FROM HangingClinics WHERE MediVisitResourceDes = \"$MediVisitResourceDes\""; 

  print "SQL: $DeleteHangingClinicSQL\n";


  # prepare query
  $query= $dbh_mysql->prepare($DeleteHangingClinicSQL)
    or die "Couldn't prepare statement: " . $dbh_mysql->errstr;

  # submit query
  $query->execute()
    or die "Couldn't execute statement: " . $query->errstr;









  $numHangingClinics++;
}
print "A total of $numHangingClinics hanging clinics were scheduled\n";



#------------------------------------------------------------------------
# exit gently
#------------------------------------------------------------------------
exit;

