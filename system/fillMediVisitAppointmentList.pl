#!/usr/bin/perl -w

# fillMediVisitAppointmentList:	Simple perl script to to read in a Medivisit
# appointment list and insert into the MySQL database
 
#------------------------------------------------------------------------
# J.Kildea
#------------------------------------------------------------------------

#------------------------------------------------------------------------
# subroutine to send email
#------------------------------------------------------------------------
sub SendEmail {
  # receive subject and body
  my ($EmailSubject, $EmailData) = @_;

  # email error
  my $mime = MIME::Lite->new(
    'From'		=> "orms\@muhc.mcgill.ca",
	'To'		=> "victor.matassa\@mail.mcgill.ca",
    # 'Cc'			=> "",
    'Subject'	=> $EmailSubject,
    'Type'		=> 'text/plain',
    'Data'		=> $EmailData
  );

  # send out email
  my $response = $mime->send('smtp', '172.25.123.208');
}

#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
use strict;
use v5.10;
use warnings;
use diagnostics;

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
use Text::CSV::Encoded;
use Date::Calc qw(Day_of_Week_Abbreviation Day_of_Week);
use MIME::Lite;

#------------------------------------------------------------------------
# Use File Control
#------------------------------------------------------------------------
use File::Copy;
use File::Basename;

#------------------------------------------------------------------------
# POSIX::strftime::GNU - strftime with GNU extensions
#------------------------------------------------------------------------
use POSIX;

#------------------------------------------------------------------------
# Internal Variables
#------------------------------------------------------------------------
my $verbose = 1;
my $help;
my $datafile;
my $wsSQL = "";
my $wsErrorMsg = "";
my $wsHoldAppointmentDescription = "";

#current location
my $fileURL = LoadConfigs::GetConfigs('path')->{'BASE_URL'};
$fileURL = $fileURL ."/system/fillMediVisitAppointmentList.pl";

#Path to the archive folder
my $ArchivePath = LoadConfigs::GetConfigs('path')->{'LOG_PATH'};
$ArchivePath = $ArchivePath ."/CCCAppsListArchive/"; 

#------------------------------------------------------------------------
# Read in the command line arguments
#------------------------------------------------------------------------
use Getopt::Long;
&GetOptions("h"	     => \$help,
	    "help"   => \$help,
	    "file=s" => \$datafile,
	   );

#------------------------------------------------------------------------
# Process the command line input
# if there are no command line arguments then say so and die!
#------------------------------------------------------------------------
if ($help || !$datafile || (!$help && !$datafile))
{
die "fillMediVisitAppointmentList: too few arguments\n

Usage:
	fillMediVisitAppointmentList --file=filename
		\n";
}

#------------------------------------------------------------------------
# Database initialisation stuff
#------------------------------------------------------------------------
my $dbh_mysql =  LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database ");

#------------------------------------------------------------------------
# Make sure that the datafile is the current date and not previous
#------------------------------------------------------------------------
my $wsCheckModifiedDate = (stat $datafile)[9];
$wsCheckModifiedDate = strftime("%Y-%m-%d", localtime($wsCheckModifiedDate));
my $wsCurrentDate = strftime("%Y-%m-%d", localtime(time));

# Compare system date and file date
if ($wsCheckModifiedDate ne $wsCurrentDate) {
  # System date and file date does not match so send an email alert
  # prepare the email message
  $wsErrorMsg = "Server : 172.26.66.41
    Module : $fileURL
    Error Message : 
    ---- The file being process is not today's date
    ---- Filename : $datafile
    ";
  
  # Send out an email
  #if its the weekend, don't send out an email since they file read will be yesterday's file
  if((localtime)[6] != 6 and (localtime)[6] != 0) #saturday and sunday
  {
  	SendEmail("ORMS ERROR - Processing File Is Not Today", $wsErrorMsg);
  }

  print "\nERROR: The file is not current\n\n";
  exit;
}

#------------------------------------------------------------------------
# Create an Achive file before processing the CSV file
#------------------------------------------------------------------------
my $ArchiveTimeStamp = strftime("%Y%m%d%H%M%S", localtime(time));
my $ArchiveFileName =  $ArchivePath . $ArchiveTimeStamp . basename($datafile,  ".csv") . ".csv";

copy($datafile, $ArchiveFileName) or die "Copy failed: $!";

#------------------------------------------------------------------------
# Open the data file for reading
#------------------------------------------------------------------------
open my $csv, "<$datafile" or die $!;

my $parser = Text::CSV->new({binary => 1, sep_char => ','});

#read/parse contents
my $lineNum = 0;
while (my $row = $parser->getline($csv))
{
  # skip the first line of headings
  $lineNum++;
  next if $lineNum < 2;

  my @columns = @{$row};

    my $colNum = 0;
    my $col;
    foreach $col (@columns)
    {
      $columns[$colNum] =~ s/"//g; 

      $colNum++;
    }

    my $PatientId	= $columns[0];
    my $LastName	= $columns[1];
    $LastName =~ s/'/\\'/g;

    my $FirstName	= $columns[2];
    $FirstName =~ s/'/\\'/g;

    my $SSN		= $columns[3];
    my $SSNExpDate	= $columns[4];

    #------------------------------------------------------------------------
    # See if the patient exists in the MySQL Patient table. If not, insert
    # NOTE: Do not update patient information. Any update needs to be verify
    #       manually to make sure that we are updating the correct record 
    #       because patient might have a temporary RAMQ
    #------------------------------------------------------------------------
    my $Patient_sql = "SELECT PatientSerNum from Patient where SSN = '$SSN'";
    print "--------------------------------------------------------------------------------\n";
    print "Patient_sql: $Patient_sql\n\n";

    my $query= $dbh_mysql->prepare($Patient_sql) or die "Couldn't prepare statement: " . $dbh_mysql->errstr;
    $query->execute() or die "Couldn't execute statement: " . $query->errstr;
    my $PatientSerNum = $query->fetchrow_array();  

    if(!$PatientSerNum) {
      my $sql_insert_patient = "INSERT INTO Patient(LastName,FirstName,SSN,SSNExpDate,PatientId) VALUES ('$LastName','$FirstName','$SSN','$SSNExpDate','$PatientId')";
      print "sql_insert_patient $sql_insert_patient\n\n";

      $query= $dbh_mysql->prepare($sql_insert_patient);
      $query->execute();

      # Encounter an SQL error. Unable to insert patient record
      if ($query->err() > 0) {

        # prepare the email message
        $wsHoldAppointmentDescription = $columns[6];

        $wsErrorMsg = "Server : 172.26.66.41
          Module : $fileURL
          Error Message : 
          ---- Unable to insert patient ID : $PatientId
          ---- Appointment Description : $wsHoldAppointmentDescription
          ";
        
        # Send out an email
        SendEmail("ORMS ERROR - Insert Patient Failed", $wsErrorMsg);

      }

      # Proceed in displaying the error message
      $query= $dbh_mysql->prepare($Patient_sql) or die "Couldn't prepare statement: " . $dbh_mysql->errstr;
      $query->execute() or die "Couldn't execute statement: " . $query->errstr;
      $PatientSerNum = $query->fetchrow_array();
    }
   
    my $Resource	= $columns[5];
    my $ResourceDesc	= $columns[6];
    my $ScheduledDate   = $columns[7];
    my $ScheduledTime   = $columns[8];
    my $AppointmentCode = $columns[9];
    my $CreationDate	= $columns[10];
    my $SeqRV		= $columns[11];
    my $AppointIdIn	= $columns[12];	
    my $AppointId	= substr($AppointIdIn, 5);
    my $ReferringPhysician = $columns[13];
	$ReferringPhysician =~ s/\\//g;
	$ReferringPhysician =~ s/,//g;

	#--------------------------------------------------------------------------------	
	#  Initialize the date variables
	#--------------------------------------------------------------------------------	
	my $year_Date       = 0;
	my $month_Date      = 0;
	my $day_Date        = 0;

    #------------------------------------------------------------------------
    # Parse the date to extract the year month and day for the ScheduledDate
    #------------------------------------------------------------------------
	
	# Check if the date format has a dash
	if(index($ScheduledDate, '-') > 0) {
		my @split_Date      = split('-', $ScheduledDate);

		# assign the date parts
		$year_Date       = $split_Date[0];
		$month_Date      = $split_Date[1];
		$day_Date        = $split_Date[2];

	# date format has a slash
	} else {
		my @split_Date      = split(/\//, $ScheduledDate);

		# assign the date parts
		$month_Date      = $split_Date[0];
		$day_Date        = $split_Date[1];
		$year_Date       = $split_Date[2];
	}
	
    my $dow  = Day_of_Week($year_Date,$month_Date,$day_Date);
    # print "Date: $year_Date,$month_Date,$day_Date @columns\n";

    if($day_Date < 10){$day_Date = "0$day_Date";}
    if($month_Date < 10){$month_Date = "0$month_Date";}

    my $ScheduledDateTime = "$year_Date-$month_Date-$day_Date $ScheduledTime:00";
    $ScheduledDate = "$year_Date-$month_Date-$day_Date";
    $ScheduledTime = "$ScheduledTime:00";
    #print "ScheduledDateTime: $ScheduledDateTime\n";

    # Figure out the day and AM/PM of the appointment
    my $day = Day_of_Week_Abbreviation($dow); 

    my $AMorPM;
    my @split_Time	= split(/:/, $ScheduledTime);
    my $hour 		= $split_Time[0];
    $AMorPM = "AM" if $hour < 13; # PM clinic starts at 1 pm
    $AMorPM = "PM" if $hour >= 13; # PM clinic starts at 1 pm

    #------------------------------------------------------------------------
    # Parse the date to extract the year month and day for the CreationDate
    #------------------------------------------------------------------------
	my $month_Date_CD		= '';
	my $day_Date_CD        	= '';
	my $year_Date_CD       	= '';

	# Check if the date format has a dash
	if(index($CreationDate, '-') > 0) {
		my @split_Date      = split('-', $CreationDate);

		# assign the date parts
		$year_Date_CD       = $split_Date[0];
		$month_Date_CD      = $split_Date[1];
		$day_Date_CD        = $split_Date[2];	
	
	# date format has a slash
	} else {	
		my @split_Date      = split(/\//, $CreationDate);

		# assign the date parts
		$month_Date_CD      = $split_Date[0];
		$day_Date_CD        = $split_Date[1];
		$year_Date_CD       = $split_Date[2];
	}
	
    my $dow_CD 		= Day_of_Week($year_Date_CD,$month_Date_CD,$day_Date_CD);
    #print "Date: $year_Date_CD,$month_Date_CD,$day_Date_CD @columns\n";

    if($day_Date_CD < 10){$day_Date_CD = "0$day_Date_CD";}
    if($month_Date_CD < 10){$month_Date_CD = "0$month_Date_CD";}

    $CreationDate = "$year_Date_CD-$month_Date_CD-$day_Date_CD";
    #print "CreationDate: $CreationDate\n";

    # As a sanity check, look to see if the Medivisit resource exists in
    # MySQL. If it does not do not enter the patient as we cannot tell them
    # where to go when they check in
    my $MediVisitResource_sql = "SELECT ClinicResourcesSerNum from ClinicResources where ResourceName = \"$ResourceDesc\"";
    print "MediVisitResource_sql: -->> $MediVisitResource_sql\n\n";

    $query= $dbh_mysql->prepare($MediVisitResource_sql) or die "Couldn't prepare statement: " . $dbh_mysql->errstr;
    $query->execute() or die "Couldn't execute statement: " . $query->errstr;
    my $ClinicResourcesSerNum = $query->fetchrow_array();

    #------------------------------------------------------------------------
    # Insert the appointment into MySQL
    # Each patient also gets a triage and a bloods appointment 
    # If the patient already has a bloods appointment in medivisit it is not entered twice due to the unique key in MySQL
    #------------------------------------------------------------------------
    my $level = "unknown"; 
    if($ClinicResourcesSerNum)
    {
      # Find out which level the appointment is on DRC or DS1
      my $level_sql = "
			SELECT ExamRoom.Level 
			FROM 
				ClinicResources,
				ClinicSchedule,
				ExamRoom
			WHERE 
				ClinicResources.ResourceName = \"$ResourceDesc\"
				AND ClinicResources.ClinicScheduleSerNum =  ClinicSchedule.ClinicScheduleSerNum
				AND ClinicSchedule.DAY =  \"$day\"
				AND ClinicSchedule.AMPM =  \"$AMorPM\"
				AND ClinicSchedule.ExamRoomSerNum = ExamRoom.ExamRoomSerNum
		       ";

      print "level_sql: $level_sql\n\n";

      $query= $dbh_mysql->prepare($level_sql) or die "Couldn't prepare statement: " . $dbh_mysql->errstr;
      $query->execute() or die "Couldn't execute statement: " . $query->errstr;
      $level = $query->fetchrow_array();  
      #$level = "unknown" if !$level;
      $level = "RC" if !$level; # send all patients that can't be assigned a correct level to RC reception

      print "level: $level\n\n";

	  # Check if the Appointment exist in the MediVisitAppointmentList
	  $wsSQL = "Select count(*) Total from MediVisitAppointmentList where AppointId = ?";
    $query= $dbh_mysql->prepare($wsSQL);
    $query->bind_param(1, $AppointId);
    $query->execute();
    my ($wsAppointmentCount) = $query->fetchrow();

    # Check if patient arrived for their appointments
	  $wsSQL = "select 
        (select count(*) from PatientLocation where AppointmentSerNum = MVAL.AppointmentSerNum) +
        (select count(*) from PatientLocationMH where AppointmentSerNum = MVAL.AppointmentSerNum)
        as Total
      from 	MediVisitAppointmentList MVAL
      where AppointId = ?";
    $query= $dbh_mysql->prepare($wsSQL);
    $query->bind_param(1, $AppointId);
    $query->execute();
    my ($wsPatientLocationCount) = $query->fetchrow();

    # If no record exist then insert new appointment
    if ($wsAppointmentCount == 0) {
      my $sql_insert_appointment = "INSERT INTO MediVisitAppointmentList (PatientSerNum, Resource, ResourceDescription, ClinicResourcesSerNum, ScheduledDateTime, ScheduledDate, 
        ScheduledTime,	AppointmentCode,	Status,	AppointId,	AppointSys,	CreationDate,	ReferringPhysician) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

      print "INSERT: $sql_insert_appointment\n\n";
      $query= $dbh_mysql->prepare($sql_insert_appointment);
      
      $query->bind_param(1, $PatientSerNum);
      $query->bind_param(2, $Resource);
      $query->bind_param(3, $ResourceDesc);
      $query->bind_param(4, $ClinicResourcesSerNum);
      $query->bind_param(5, $ScheduledDateTime);
      $query->bind_param(6, $ScheduledDate);
      $query->bind_param(7, $ScheduledTime);
      $query->bind_param(8, $AppointmentCode);
      $query->bind_param(9, "Open");
      $query->bind_param(10, $AppointId);
      $query->bind_param(11, "Impromptu");
      $query->bind_param(12, $CreationDate);
      $query->bind_param(13, $ReferringPhysician);
	  
      $query->execute(); # ignore errors and duplicates

#      # Insert the triage appointment - set for 6 am today
#      my $fake_apt_datetime = "$year_Date-$month_Date-$day_Date 06:00:00";
#      my $fake_apt_date = "$year_Date-$month_Date-$day_Date";
#      my $fake_apt_time = "06:00:00";
#      $Resource = "TRIAGE - $level RECEPTION";
#      $ResourceDesc = $Resource;  
#      $AppointmentCode = "TRIAGE";
#      $sql_insert_appointment = "INSERT INTO MediVisitAppointmentList (PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,Status) VALUES ('$PatientSerNum','$Resource','$ResourceDesc','$ClinicResourcesSerNum','$fake_apt_datetime','$fake_apt_date','$fake_apt_time','$AppointmentCode','Open')";
#      $query= $dbh_mysql->prepare($sql_insert_appointment);
#      $query->execute(); # ignore errors and duplicates

#      # Insert the bloods appointment - set for 6:30 am today
#      $fake_apt_datetime = "$year_Date-$month_Date-$day_Date 06:30:00";
#      $fake_apt_date = "$year_Date-$month_Date-$day_Date";
#      $fake_apt_time = "06:30:00";
#      $Resource = "NSBLD";
#      $ResourceDesc = "NS - prise de sang/blood tests pre/post tx";  
#      $AppointmentCode = "BLD-XY";
#      $sql_insert_appointment = "INSERT INTO MediVisitAppointmentList (PatientSerNum,Resource,ResourceDescription,ClinicResourcesSerNum,ScheduledDateTime,ScheduledDate,ScheduledTime,AppointmentCode,Status) VALUES ('$PatientSerNum','$Resource','$ResourceDesc','$ClinicResourcesSerNum','$fake_apt_datetime','$fake_apt_date','$fake_apt_time','$AppointmentCode','Open')";
#      $query= $dbh_mysql->prepare($sql_insert_appointment);
#      $query->execute(); # ignore errors and duplicates

      # Patient appointment exist in database. Check if patient arrived for their appointments
    } elsif ($wsPatientLocationCount == 0) {
        # Update patient appointment because they did not arrive yet.
        print "PATIENT EXIST: Updating appointment for PatientSerNum " . $PatientSerNum . "\n\n";
        $wsSQL = "Update MediVisitAppointmentList
                      Set PatientSerNum = ?,
                      Resource = ?,
                      ResourceDescription = ?,
                      ClinicResourcesSerNum = ?,
                      ScheduledDateTime = ?,
                      ScheduledDate = ?,
                      ScheduledTime = ?,
                      AppointmentCode = ?,
                      Status = ?,
                      AppointSys = ?,
                      CreationDate = ?,
                      ReferringPhysician = ?
                    Where AppointId = ?";

        $query= $dbh_mysql->prepare($wsSQL);
        
        $query->bind_param(1, $PatientSerNum);
        $query->bind_param(2, $Resource);
        $query->bind_param(3, $ResourceDesc);
        $query->bind_param(4, $ClinicResourcesSerNum);
        $query->bind_param(5, $ScheduledDateTime);
        $query->bind_param(6, $ScheduledDate);
        $query->bind_param(7, $ScheduledTime);
        $query->bind_param(8, $AppointmentCode);
        $query->bind_param(9, "Open");
        $query->bind_param(10, "Impromptu");
        $query->bind_param(11, $CreationDate);
        $query->bind_param(12, $ReferringPhysician);
        $query->bind_param(13, $AppointId);
      
        $query->execute(); # ignore errors and duplicates
    } else {
      # Patient already arrived for their appointments
      print "UPDATE ABORTED: PatientSerNum $PatientSerNum already checked into their appointments\n\n";
    }

    }
    else
    {
      ########################### Make a list of unknown clinics #################################3
      print "Clinic $ResourceDesc does not appear to exist in MySQL. Not entering patient $LastName for this clinic\n\n";
      my $sql_insert_hangingclinic = "INSERT INTO HangingClinics(MediVisitName,MediVisitResourceDes) VALUES (\"$Resource\",\"$ResourceDesc\")";
     
      #$query= $dbh_mysql->prepare($sql_insert_hangingclinic) or die "Couldn't prepare statement: " . $dbh_mysql->errstr;
      $query= $dbh_mysql->prepare($sql_insert_hangingclinic);

      $query->execute(); # ignore errors

      $wsErrorMsg = "Server : 172.26.66.41
        Module : $fileURL
        Error Message : 
        ---- Clinic $ResourceDesc does not appear to exist in MySQL. Not entering patient $LastName for this clinic
        ";
      
      # Send out an email
      SendEmail("ORMS ERROR - Hanging Clinic (Unknown Clinic)", $wsErrorMsg);

    }
}
close $csv;

#------------------------------------------------------------------------
# exit gently
#------------------------------------------------------------------------
exit;
