#!/opt/perl5/perl

#------------------------------------------------------------------------
# PERL-CGI- script to check a patient in 
# 
# Input parameters: 	various	
#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
$SIG{__DIE__} = sendToReception;
use strict;
use v5.30;
use lib "./system/modules";
#use warnings;
#use diagnostics;
use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Date::Calc;
#use Date::Calc qw( Standard_to_Business Today Business_to_Standard );  
use Date::Calc qw(Day_of_Week_to_Text Day_of_Week Decode_Month Today Now Decode_Date_US Today_and_Now Delta_DHMS Add_Delta_Days Delta_Days Add_Delta_DHMS Day_of_Week_Abbreviation);
use Time::Piece;

use LoadConfigs;

#------------------------------------------------------------------------
# Modules needed for SOAP webservices
#------------------------------------------------------------------------
use HospitalADT;
use Data::Dumper;

#------------------------------------------------------------------------
# Important variables
#------------------------------------------------------------------------
my $systemPaths = LoadConfigs::GetConfigs("path");

my $checkin_script 	= "$systemPaths->{'BASE_URL'}/perl/CheckIn.pl"; 
my @resource_list 	= getResourceList(); #Which resources are in pilot
my $images 		= $systemPaths->{'IMAGE_URL'};
my $SMS_url		= "$systemPaths->{'BASE_PATH'}/php/script/sendSMSCheckedIn.php";
my $logfile_location 	= "$systemPaths->{'LOG_PATH'}/kiosk"; 
my $logging = 1; # Log data to a text file
my $PhotoStatus;
my $PilotStatus;
my $ReloadFinal = 6; # 10 second to reload the final screen
#my $ReloadMid = 3; # 3 seconds to display the patient name by default 
my $ReloadMid = 8; # 8 seconds to display the patient name by default - Measles

#------------------------------------------------------------------------
# Start the webpage here so it can be used in verbose mode
#------------------------------------------------------------------------
print "Content-type: text/html\n\n";

#------------------------------------------------------------------------
# Parse the input parameters
# verbose shows debugging information
# location is the location of the kiosk - directions are relative to this location
# PatientId is the ID of the patient that is being checked in
# PatientSer is the Aria PatientSer of the patient that is being checked in 
# PatientSerNum is the MySQL PatientSerNum (standin for Medivisit) of the patient that is being checked in 
#------------------------------------------------------------------------
my $verbose = 0;
my $verbose 		= param("verbose");
my $location		= param("location");
my $PatientId		= param("PatientId");
my $PatientSer		= param("PatientSer");
my $PatientSerNum	= param("PatientSerNum");
my $PhotoStatus		= param("PhotoStatus");
my $PilotStatus		= param("PilotStatus");

my $verboselink;

my $flashBorder = 1;

my $border;
if($verbose)
{
  $border = "1";
  $verboselink = "0";
  $ReloadFinal = 400;
  $ReloadMid = 10;
}
else
{
  $border = "0";
  $verboselink = "1";
}

# Let's use the DS1_1 as the default location
$location = "DS1_1" if !$location;

#------------------------------------------------------------------------
#                        Location-specific stuff
#
# Determine the hospital from the location - needed to know which MRN to use
# Determine whether to use or not use Aria depdending on location
# Aria is only used in Cancer Centre DS1 and DRC
#------------------------------------------------------------------------
my $hospitalAria; 
my $hospitalMV;
my $useAria;
my $mrnType;#Used in check on Patient MRN type in isramqExpired function
if($location eq "Ortho_1" || $location eq "Ortho_2")
{
  # Uncomment when ready to implement
  #$hospitalAria = "2"; # MGH MRN is PatientId2 in Aria 
  #$hospitalMV = "_MGH"; # MGH MRN is PatientId_MGH in MV MySQL DB
  print "mrnType is MG\n" if $verbose;
  $mrnType = "MG"; 
  $hospitalAria = ""; #nothing for Glen right now
  $hospitalMV = ""; #nothing for Glen right now
  $useAria = 0; # no Aria when in Ortho
  print "Not using Aria <br>\n" if $verbose;
}
else
{
  print "mrnType is MR\n" if $verbose;
  $mrnType = "MR";
  $hospitalAria = ""; #nothing for Glen right now
  $hospitalMV = ""; #nothing for Glen right now

  $useAria = 1; # use Aria when not Ortho
  print "Using Aria <br>\n" if $verbose;
}



#------------------------------------------------------------------------
# Time and date stuff
#------------------------------------------------------------------------
# Times may be set up for searching the database for appointments
# For example, we may wish to only check in patients for future appointments today and ignore appointments that they are late for
# We may also choose to allow patients to check in for appointments 30 minutes late, 60 minutes late or for the first appointment 
# of the day 
#------------------------------------------------------------------------
my ($year_today,$month_today,$day_today, $hour_today,$min_today,$sec_today) = Today_and_Now();
my $now = "$month_today/$day_today/$year_today $hour_today:$min_today:$sec_today";
my $today_00 = "$month_today/$day_today/$year_today 00:00:00";

# Put today's date on the logfile
my $checkin_logfile = "$logfile_location/logfile_$month_today$day_today$year_today.html";
open(CHECKINLOG, ">>$checkin_logfile") || die "file $checkin_logfile does not exist or may have permission problems";

# Get the time to start the appointment search 

my $begin= $today_00;
my $begin_mysql = "$year_today-$month_today-$day_today 00:00:00";

# End the allowed appointment time range at the end of the day today
my $end = "$month_today/$day_today/$year_today 11:59:59PM";
my $end_mysql = "$year_today-$month_today-$day_today 23:59:59";

# Get the day of the week for today
my $DayOfWeek = Day_of_Week($year_today,$month_today,$day_today);
my $DayOfWeek_Abbreviated = Day_of_Week_Abbreviation($DayOfWeek); 

#------------------------------------------------------------------------
# Determine if morning or afternoon clinic - 
# morning < 13h = use AM
# afternoon >=13h use PM
#------------------------------------------------------------------------
my $AMorPM = "AM";
my $MorningAfternoon = "Morning";
if($hour_today >= 13)
{
  $AMorPM = "PM";
  $MorningAfternoon = "Afternoon";
}

my $DayOfWeek_Text = Day_of_Week_to_Text($DayOfWeek);
print "Today is: $DayOfWeek_Text ($MorningAfternoon) - now: $now<br>" if $verbose;

#------------------------------------------------------------------------
# The Webpage format
#------------------------------------------------------------------------
# Logo
# Location symbol (either DRC_1, DS1_1 or DRC_2 - these correspond to the locations of the kiosks and allow us to see at 
# a glance where the program is running, it should be correctly set for its location) 
# French main message
# French sub message
# Image with arrows
# English main message
# English sub message
# Input text box

#------------------------------------------------------------------------
# Colours for the webpage
#------------------------------------------------------------------------
my $red 	= "rgb(255,	0,	0)";
my $green 	= "rgb(34,	139,	34)";
my $blue	= "rgb(0,	0,	255)";
my $black 	= "rgb(0,	0,	0)";
my $white 	= "rgb(255,	255,	255)";
my $gray 	= "rgb(128,	128, 	128)";
my $lightgray 	= "rgb(224,	224,	224)";
my $paleblue 	= "rgb(173,	216,	230)";
my $palered	= "rgb(240,	128,	128)";
my $palegreen 	= "rgb(152,	251,	152)";
my $darkgreen 	= "rgb(51,	153,	51)";
my $mghred	= "rgb(162,	53,	96)";
#my $darkgreen 	= "rgb(0,	102, 	0)";


# Variable for arrows
my $arrows = 1;

# Message goes at the top
my $message_txtcolor 		= $black;
my $message_bgcolor 		= $darkgreen;


# Ortho kiosks: The kiosk outside is Ortho_1 and inside is Ortho_2
if($location eq "Ortho_1" || $location eq "Ortho_2")
{
  $message_bgcolor = $mghred; 
}

if($location =~ m/Reception/i)
{
  $message_bgcolor = $blue; 
}


my $message_txtcolor 		= $white;

my $MainMessage_fr 			= "Enregistrement";
my $MainMessage_en 			= "Check in";

# Appointment details go below the message
my $subMessage_bgcolor 	= $white;
my $subMessage_txtcolor 	= $black;
#my $subMessage_fr 		= "<center>Veuillez scanner votre carte soleil pour vous enregistrer.<br> <span style=\"background-color: #FFFFE0\"><b><font color='red'>Veuillez tenir votre carte &agrave environ 10 cm du lecteur.</font></b></span></center>";
my $subMessage_fr 		= "<center>Veuillez scanner votre carte soleil pour vous enregistrer.<br> </center>";
#my $subMessage_en 		= "<center>Please scan your medicare card to check in.<br> <span style=\"background-color: #FFFFE0\"><b><font color='red'>Please hold card about 10 cm from scanner.</font></b></span></center>";
my $subMessage_en 		= "<center>Please scan your medicare card to check in.<br> </center>";
my $log_message; 

# Appointment details go below the message
my $middleMessage_bgcolor 	= $white;
my $middleMessage_txtcolor 	= $black;

# Instructions are found to the left of the photo
my $middleMessage_bgcolor 	= $white;
my $middleMessage_txtcolor 	= $black;
my $middleMessage_fr 		= "";
my $middleMessage_en 		= "";


# Use a refresh to continuously refresh the page - this ensures that Google Chrome does not crash the page thinking it 
# is inactive. It also allows for automatic updates and provides continuous logging to the log file to remotely check 
# that all the kiosks are still alive
my $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"200\; URL=$checkin_script?location=$location&verbose=$verbose\">";

my $shortline = "<img src=\"$images/line_short.png\">";
$shortline = "" if $location =~ m/Reception/i;

my $middleMessage_image = "<img border=\"$flashBorder\" width=\"614\" src=\"$images/animation.gif\">";
$middleMessage_image = "<img border=\"$flashBorder\" width=\"214\" src=\"$images/animation.gif\">" if $location =~ m/Reception/i;
$log_message 		= "default message, $location, $subMessage_en";

# Waiting Room details
my $DestinationWaitingRoom;

#------------------------------------------------------------------------
# Set the location - can be done using location barcodes (DRC_1, DS1_1, DRC_2)
#------------------------------------------------------------------------
my $DRC_1 	= "<img src=\"$images/DRC_1_Alone.gif\">";
my $DRC_2 	= "<img src=\"$images/DRC_2_Alone.gif\">";
my $DRC_3 	= "<img src=\"$images/DRC_3_Alone.gif\">";

my $DS1_1 	= "<img src=\"$images/DS1_1_Alone.gif\">";
my $DS1_2 	= "<img src=\"$images/DS1_2_Alone.gif\">";

my $Ortho_1 	= "<img src=\"$images/Ortho_1_Alone.gif\">";
my $Ortho_2 	= "<img src=\"$images/Ortho_2_Alone.gif\">";

my $location_image;
$location_image = $DRC_1 if $location eq "DRC_1";
$location_image = $DRC_2 if $location eq "DRC_2";
$location_image = $DRC_3 if $location eq "DRC_3";

$location_image = $DS1_1 if $location eq "DS1_1";
$location_image = $DS1_2 if $location eq "DS1_2";

$location_image = $Ortho_1 if $location eq "Ortho_1";
$location_image = $Ortho_2 if $location eq "Ortho_2";

# Set the location using barcodes
if($PatientId eq "DRC_1")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DRC_1\">" ;
  printUI();
}
if($PatientId eq "DS1_1")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DS1_1\">" ;
  printUI();
}
if($PatientId eq "DS1_2")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DS1_2\">" ;
  printUI();
}

if($PatientId eq "DRC_1")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DRC_1\">" ;
  printUI();
}
if($PatientId eq "DRC_2")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DRC_2\">" ;
  printUI();
}
if($PatientId eq "DRC_3")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DRC_3\">" ;
  printUI();
}

if($PatientId eq "verbose")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=$location&verbose=1\">" ;
  printUI();
}

#------------------------------------------------------------------------
# Search for patient
# Take the patient Id and find the patient - if we have a PatientSer or 
# PatientSerNum it means  we have already located the patient in the db 
#------------------------------------------------------------------------
my ($PatientLastName,$PatientFirstName,$PatientDisplayName,$RAMQExpired);
if( $PatientId && (!$PatientSer && !$PatientSerNum) ) 
{
  print "Patient ID: $PatientId<br>" if $verbose;
  #****************************************************************************
  #****************************************************************************
  # First ****FIND**** the patient and let him/her know
  ($PatientLastName,$PatientFirstName,$PatientSer,$PatientSerNum,$PatientDisplayName,$RAMQExpired) = findPatient();
  #****************************************************************************
  #****************************************************************************

  print "PatientSer: $PatientSer<br>" if $verbose;
  print "PatientSerNum: $PatientSerNum<br>" if $verbose;
  print "PhotoStatus: $PhotoStatus<br>" if $verbose;
  print "PilotStatus: $PilotStatus<br>" if $verbose;

  # A NULL PatientSer means no patient found
  if($PatientSer eq "NULL" && $PatientSerNum eq "NULL")
  {
    # Tell the patient to go to the reception since he/she has not been located
    #$message_bgcolor 		= $darkgreen;
    $message_txtcolor 		= $white;
    $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en 		= "Please go to the reception";
    $subMessage_fr 		= "Impossible de vous enregistrer en ce moment";  
    $subMessage_en 		= "Unable to check you in at this time";  
    $middleMessage_fr 		= "<b></b>";
    $middleMessage_en 		= "<b></b>";
    $arrows 			= 1;
    $DestinationWaitingRoom	= "reception";
    #$middleMessage_image 	= "<img src=\"$images/reception_alone.png\">";
    $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message 		= "$PatientId, $location, $subMessage_en";
  }
  # Expired RAMQ - send to admitting 
  elsif($RAMQExpired eq 1)
  {
     # Tell the patient to go to the reception since he/she has not been located
     #$message_bgcolor 		= $darkgreen;
     $message_txtcolor 		= $white;
     $MainMessage_fr 		= "Carte d'h&ocirc;pital expir&eacute;e";
     $MainMessage_en 		= "Hospital Card Expired";

     $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">Veuillez vous rendre au bureau des admissions &agrave; <b>C RC.0046</b> pour renouveler votre carte d'h&ocirc;pital.</span><br>"; 
     $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">Veuillez vous rendre au bureau des admissions &agrave; <b>L6-130</b> pour renouveler votre carte d'h&ocirc;pital.</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2"); 
     #$subMessage_fr 		= "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">V&eacute;rifier &agrave la r&eacute;ception</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2"); 

     $subMessage_en 		= "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to Admitting at <b>C RC.0046</b> to renew your hospital card.</span><br>";
     $subMessage_en 		= "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to Admitting at <b>L6-130</b> to renew your hospital card.</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");
     #$subMessage_en 		= "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to the reception</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");

     $middleMessage_fr 		= "<b></b>";
     $middleMessage_en 		= "<b></b>";
     $arrows 			= 1;
     $DestinationWaitingRoom	= "MGH_Admissions";
     #$middleMessage_image 	= "<img src=\"$images/reception_alone.png\">";
     $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
     $middleMessage_image 	= "<img width=\"614\" src=\"$images/RV_Admissions.png\">";
     $middleMessage_image 	= "<img width=\"614\" src=\"$images/MGH_Admissions.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");
     #$middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

     # reload to the default page after 20 seconds
     $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

     $log_message 		= "$PatientId, $location, $subMessage_en";
  }
  else # We have a PatientSer, so a patient has been found
  {
    # print the patient's name to reassure them and display an interim message with a temporary spinning circle to give the impression that the kiosk 
    # is doing something
    $MainMessage_fr = "Veuillez patienter...";
    $MainMessage_en = "Please wait...";
    #$subMessage_fr = "R&eacute;cuperation de donn&eacute;es en cours pour <br><span style=\"background-color: #ffff00\">$PatientFirstName $PatientLastName<span>";  
    #$subMessage_fr = "R&eacute;cuperation de donn&eacute;es en cours pour <span style=\"background-color: #ffff00\">$PatientDisplayName<span>";  
    $subMessage_fr = "R&eacute;cuperation de donn&eacute;es <span style=\"background-color: #ffff00\">$PatientDisplayName<span>";  
    $subMessage_en = "Retrieving information for <span style=\"background-color: #ffff00\">$PatientDisplayName</span>";  
    $middleMessage_image = "<img src=\"$images/Measles.png\"><p>"; #waiting.gif
    $arrows 			= 0;
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$ReloadMid\; URL=$checkin_script?PatientId=$PatientId&PatientSer=$PatientSer&PatientSerNum=$PatientSerNum&location=$location&verbose=$verbose\">";

    $log_message 		= "$PatientId, $location, $subMessage_en";
  }

  # print the screen
  printUI();
}
elsif( $PatientId && ($PatientSer || $PatientSerNum) ) # Patient already found, now check in
{


  #****************************************************************************
  #****************************************************************************
  # attempt to check the patient in for his/her ***NEXT*** appointment TODAY
  # Both check in and retrieve the appointment information at the same time
  my ($CheckinStatus,$ScheduledStartTime_en,$ScheduledStartTime_fr,$Appt_type_en,$Appt_type_fr,$WaitingRoom,$TreatmentRoom,$PhotoStatus,$PilotStatus,$System) = CheckinPatient($PatientSer,$PatientSerNum,$begin,$begin_mysql,$end,$end_mysql); 

  print "checkin code: ($CheckinStatus,$ScheduledStartTime_en,$ScheduledStartTime_fr,$Appt_type_en,$Appt_type_fr,$WaitingRoom,$TreatmentRoom,$PhotoStatus,$PilotStatus,$System) = CheckinPatient($PatientSer,$PatientSerNum,$begin,$begin_mysql,$end,$end_mysql)<br>" if $verbose; 
  #****************************************************************************
  #****************************************************************************

  #============================================================================
  # Send SMS Message, will go to patient if he/she is registered in ORMS 
  #============================================================================
  if($CheckinStatus eq "OK")
  {
    my $message_EN = "MUHC - Cedars Cancer Centre: You are checked in for your appointment(s).";
    my $message_FR = "CUSM - Centre du cancer des Cèdres: Votre(vos) rendez-vous est(sont) enregistré(s)";

    if($location eq "Ortho_1" || $location eq "Ortho_2"|| $location eq "ReceptionOrtho")
    {
	$message_EN = "MGH - Orthopedics: You are checked in for your appointment(s).";
	$message_FR = "HGM - Orthopédie: Votre(vos) rendez-vous est(sont) enregistré(s)";
    }

    my $SMS_message = "php $SMS_url --PatientId=\"$PatientId\" --message_EN=\"$message_EN\" --message_FR=\"$message_FR\"";
    my $response = `$SMS_message`;

    print "SMS_message: $SMS_message<br>" if $verbose;

    if($response =~ /Message should have been sent/)
    {
      print "Successful SMS: $response<br>" if $verbose;
      print CHECKINLOG "SMS success -> SMS_message: $SMS_message --- Response: $response\n";
    }
    else
    {
      print "Problematic SMS: $response<br>" if $verbose;
      print CHECKINLOG "SMS fail -> SMS_message: $SMS_message --- Response: $response\n";
    }
  }
  else
  {
    my $message_EN = "MUHC - Cedars Cancer Centre: Unable to check-in for one or more of your appointment(s). Please go to a reception.";
    my $message_FR = "CUSM - Centre du cancer des Cèdres: Impossible d'enregistrer un ou plusieurs de vos rendez-vous. SVP vérifier à la réception";

    if($location eq "Ortho_1" || $location eq "Ortho_2"|| $location eq "ReceptionOrtho")
    {
	$message_EN = "MGH - Orthopedics: Unable to check-in for one or more of your appointment(s). Please go to a reception.";
	$message_FR = "HGM - Orthopédie: Impossible d'enregistrer un ou plusieurs de vos rendez-vous. SVP vérifier à la réception";
    }

    my $SMS_message = "php $SMS_url --PatientId=\"$PatientId\" --message_EN=\"$message_EN\" --message_FR=\"$message_FR\"";
    my $response = `$SMS_message`;

    print "SMS_message: $SMS_message<br>" if $verbose;

    if($response =~ /Message should have been sent/)
    {
      print "Successful SMS: $response<br>" if $verbose;
      print CHECKINLOG "SMS success -> SMS_message: $SMS_message --- Response: $response\n";
    }
    else
    {
      print "Problematic SMS: $response<br>" if $verbose;
      print CHECKINLOG "SMS fail -> SMS_message: $SMS_message --- Response: $response\n";
    }
  }

  #============================================================================

  $DestinationWaitingRoom = $WaitingRoom;
  print "DestinationWaitingRoom: $DestinationWaitingRoom<br>" if $verbose;

  # Report back to the user
  # if checked in fine, report success by saying "Checked In" and give time of appointment 
  if($CheckinStatus eq "OK" && $ScheduledStartTime_en)
  {
    #$message_bgcolor 		= $green;
    $message_txtcolor 		= $white;

    # Check the patient destination and give appropriate directions - generally, tell the patient to go to a waiting room
    print "TreatmentRoom: $TreatmentRoom" if $verbose;
#    if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i)
#    { 
#      $subMessage_fr 		= "Veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur l'&eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.<br/><b>Rendez-vous :</b> $Appt_type_fr ($ScheduledStartTime_fr)";
#      $subMessage_en 		= "Please have a seat in the waiting room. Your name will appear on the screen when you are called.<br/><b>Appointment:</b> $Appt_type_en ($ScheduledStartTime_en)";  
#      $log_message 		= "$PatientId, $location, $subMessage_en";
#    }
    my $Aptinfo_en;
    my $Aptinfo_fr;
    #$Aptinfo_fr = "<b> Rendez-vous :</b> <span style=\"background-color: #ffff00\">$Appt_type_fr ($ScheduledStartTime_fr)</span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    #$Aptinfo_en = "<b> Appointment:</b> <span style=\"background-color: #ffff00\">$Appt_type_en ($ScheduledStartTime_en)</span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    $Aptinfo_fr = "<b> Rendez-vous :</b> <span style=\"background-color: #ffff00\">$Appt_type_fr </span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    $Aptinfo_en = "<b> Appointment:</b> <span style=\"background-color: #ffff00\">$Appt_type_en </span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    
    # destination is upstairs but checkin location is downstairs
    if($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "DRC" && ($location eq "DS1_1" || $location eq "DS1_2"))  
    {
      $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
      $MainMessage_en 		= "Please go to the reception";
      $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Veuillez enregistrer &agrave la r&eacute;ception <b>en haut au rez de chauss&eacute;e</b>.</span> ";
      $subMessage_en 		= "<span style=\"background-color: #ff0000\">Please check in at the reception <b>upstairs on the ground floor</b>.</span>";
      #$subMessage_fr 		= "<span style=\"background-color: #ff0000\">Veuillez prendre place dans la salle d'attente <b>en haut au rez de chauss&eacute;e</b>.</span> $Aptinfo_fr";
      #$subMessage_en 		= "<span style=\"background-color: #ff0000\">Please have a seat in the waiting room <b>upstairs on the ground floor</b>.</span> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    # destination is TestCentre and checkin location is upstairs
    elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "TestCentre" && ($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3"))  
    {
      $MainMessage_fr 		= "Vous &ecirc;tes enregistr&eacute;";
      $MainMessage_en 		= "You are Checked In";
      $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Si vous n'avez pas encore fait votre pr&eacute;l&egrave;vement sanguin, veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement.</span> $Aptinfo_fr Autrement, veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.";
      $subMessage_en 		= "<span style=\"background-color: #ff0000\">If you did not already have your blood test, please check in at the Test Centre Reception</b>.</span> $Aptinfo_en Otherwise, please have a seat in the waiting room. Your name will appear on the TV screens when you are called.";  
      #$subMessage_fr 		= "<span style=\"background-color: #ff0000\">Veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement.</span> $Aptinfo_fr";
      #$subMessage_en 		= "<span style=\"background-color: #ff0000\">Please checkin at the Test Centre Reception</b>.</span> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    # destination is TestCentre and checkin location is downstairs 
    elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "TestCentre" && ($location eq "DS1_1" || $location eq "DS1_2" || $location eq "DS1_3"))  
    {
      $MainMessage_fr 		= "Centre de pr&eacute;l&egrave;vement";
      $MainMessage_en 		= "Test Centre";
      $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Si vous n'avez pas encore fait votre prélèvement sanguin, veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement <b>en haut au rez de chauss&eacute;e</b>.</span> $Aptinfo_fr";
      $subMessage_en 		= "<span style=\"background-color: #ff0000\">If you did not already have your blood test, please checkin at the Test Centre Reception</b> <b>upstairs on the ground floor</b>.</span> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    # destination is downstairs but checkin location is upstairs
    elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "DS1" && ($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3"))  
    {
      $MainMessage_fr 		= "Vous &ecirc;tes enregistr&eacute;";
      $MainMessage_en 		= "You are Checked In";
      $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Veuillez prendre place dans la salle d'attente <b>en bas au sous-sol</b>.</span> $Aptinfo_fr";
      $subMessage_en 		= "<span style=\"background-color: #ff0000\">Please have a seat in the waiting room <b>downstairs on level S1</b>.</span> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    # destination is OrthoWaitRoom and checkin location is Ortho_1 or Ortho_2 
    elsif($PhotoStatus == 1 && $DestinationWaitingRoom  eq "OrthoWaitRoom" && ($location eq "Ortho_1" || $location eq "Ortho_2")) 
    {
      $MainMessage_fr 		= "Vous &ecirc;tes enregistr&eacute;";
      $MainMessage_en 		= "You are Checked In";
      #$MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
      #$MainMessage_en 		= "Please go to the reception";
      #$subMessage_fr 		= "Veuillez prendre place dans la salle d'attente. <span style=\"background-color: #ffff00\">Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.</span><br> $Aptinfo_fr";
      #$subMessage_en 		= "Please have a seat in the waiting room. <span style=\"background-color: #ffff00\">Your name will appear on the TV screens when you are called.</span><br> $Aptinfo_en";  
      $subMessage_fr 		= "Veuillez vous pr&eacute;senter &agrave la <span style=\"background-color: #ffff00\">r&eacute;ception</span> pour compl&eacute;ter votre enregistrement.<br> $Aptinfo_fr";
      $subMessage_en 		= "Please go to the <span style=\"background-color: #ffff00\">reception</span> to complete the check-in process.<br> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    # destination is RadiologyMGH and checkin location is Ortho_1 or Ortho_2 
    elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "RadiologyMGH" && ($location eq "Ortho_1" || $location eq "Ortho_2"))  
    {
      $MainMessage_fr 		= "Radiographie";
      $MainMessage_en 		= "Go for X-ray";
      $subMessage_fr 		= "<span style=\"background-color: #ff0000\">Veuillez-vous enregistrer au C5-163 pour la radiographie.</span> &Agrave votre retour, veuillez <u>repasser votre carte</u>. ";
      $subMessage_en 		= "<span style=\"background-color: #ff0000\">Please register at C5-163 for an X-ray.</span> Afterwards, please come back here and <u>check-in again</u>. ";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

    }
    elsif($PilotStatus == 1 && $PhotoStatus == 1) # destination is same as checkin location
    { 
      $MainMessage_fr 		= "Vous &ecirc;tes enregistr&eacute;";
      $MainMessage_en 		= "You are Checked In";
      $subMessage_fr 		= "Veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.<br> $Aptinfo_fr";
      $subMessage_en 		= "Please have a seat in the waiting room. Your name will appear on the TV screens when you are called.<br> $Aptinfo_en";  
      $log_message 		= "$PatientId, $location, $subMessage_en";

      # Estimated waiting time option
      #$subMessage_fr 		= "<b>Rendez-vous :</b> $Appt_type_fr<br><b>Horaire pr&eacutevu :</b> $ScheduledStartTime_fr <b>Horaire r&eacutevis&eacute :</b> $ScheduledStartTime_en<br><b>Temps d'attente estim&eacute :</b> <span style=\"background-color: #ffff00\">20 mins &agrave partir de maintenant</span><br><b>Message :</b> <span style=\"background-color: #FF6666\">bla bla bla</span>";  
    
      #$subMessage_en 		= "<b>Appointment:</b> $Appt_type_en<br><b>Scheduled:</b> $ScheduledStartTime_en<br><b>Expected:</b> <span style=\"background-color: #ffff00\">$ScheduledStartTime_en</span><br><b>Estimated wait:</b> <span style=\"background-color: #ffff00\">20 mins from now</span><br><b>Message :</b><span style=\"background-color: #FF6666\"> bla bla bla</span>";  
    }
    elsif($PilotStatus == 1 && $PhotoStatus == 0) # No photo in Aria (Note, patient has successfully checked in)
    {
      # Tell the patient to go to the reception for photo 
      #$message_bgcolor 		= $darkgreen;
      $message_txtcolor 	= $white;
      $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
      $MainMessage_en 		= "Please go to the reception";
      $subMessage_fr 		= "Veuillez vous pr&eacute;senter &agrave la r&eacute;ception <span style=\"background-color: #FFFFE0\"><b><font color='red'><b>pour que l'on vous prenne en photo.</font></b></span>";  
      $subMessage_en 		= "Please go to the reception <span style=\"background-color: #FFFFE0\"><b><font color='red'>to have your photo taken.</font></b></span><b></b>";  
      $middleMessage_fr 	= "<b></b>";
      $middleMessage_en 	= "<b></b>";
      $arrows 			= 1;
      $DestinationWaitingRoom	= "reception";
      #$middleMessage_image 	= "<img src=\"$images/reception_alone.png\">";
      $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
      $middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

      # reload to the default page after 20 seconds
      $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

      $log_message 		= "$PatientId, $location, $subMessage_en";
    }
    else # Not in the medivisit pilot, although successfully checked in 
    {
      # Tell the patient to go to the reception 
      #$message_bgcolor 		= $darkgreen;
      $message_txtcolor 	= $white;
      $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
      $MainMessage_en 		= "Please go to the reception";
      $subMessage_fr 		= "Veuillez vous pr&eacute;senter &agrave la r&eacute;ception";  
      $subMessage_en 		= "Please go to the reception";  
      $middleMessage_fr 	= "<b></b>";
      $middleMessage_en 	= "<b></b>";
      $arrows 			= 1;
      $DestinationWaitingRoom	= "reception";
      #$middleMessage_image 	= "<img src=\"$images/reception_alone.png\">";
      $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
      $middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

      # reload to the default page after 20 seconds
      $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

      $log_message 		= "$PatientId, $location, $subMessage_en";
    }
    $arrows 			= 1;

    print "Waiting room symbol: $DestinationWaitingRoom<br>" if $verbose;

    if($DestinationWaitingRoom eq "DS1") 
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      $middleMessage_image 		= "<img src=\"$images/salle_DS1.png\">";
    }
    if($DestinationWaitingRoom eq "DRC")
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      #$middleMessage_image 		= "<img src=\"$images/salle_DRC.png\">";
      $middleMessage_image 		= "<img width=\"700\" src=\"$images/DRC_medivisit.png\">";
    }

    if($DestinationWaitingRoom =~ m/TB_/i || $DestinationWaitingRoom =~ m/STX_/i)
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      $middleMessage_image 		= "<img src=\"$images/$DestinationWaitingRoom.png\">";
    }

    if($DestinationWaitingRoom eq "TestCentre")
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      $middleMessage_image 		= "<img src=\"$images/$DestinationWaitingRoom.png\">";
    }

    if($DestinationWaitingRoom eq "RadiologyMGH")
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      $middleMessage_image 		= "<img width=\"614\" src=\"$images/$DestinationWaitingRoom.png\">";
    }
    if($DestinationWaitingRoom eq "OrthoWaitRoom")
    {
      $middleMessage_fr 		= "";
      $middleMessage_en 		= "";
      $middleMessage_image 		= "<img width=\"614\" src=\"$images/$DestinationWaitingRoom.png\">";
    }

  }
  elsif( $CheckinStatus ne "OK" || !$Appt_type_en) # No appointment found - send to the reception 
  {
    #$message_bgcolor 		= $darkgreen;
    $message_txtcolor 		= $white;
    $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en 		= "Please go to the reception";
    $subMessage_fr 		= "Impossible de vous enregistrer en ce moment";  
    $subMessage_en 		= "Unable to check you in at this time";  
    $middleMessage_fr 		= "<b></b>";
    $middleMessage_en 		= "<b></b>";
    $arrows 			= 1;
    $DestinationWaitingRoom	= "reception";
    $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message 		= "$PatientId, $location, $subMessage_en";
  }  

  # set the resulting webpage to reload to the default page after 20 seconds so that the next patient can check in
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$ReloadFinal\; URL=$checkin_script?location=$location&verbose=$verbose\">";

  # Report outcome of check in attempt to the user
  printUI(1);
}
else
{
  # default start up screen
  printUI(1);
}

#------------------------------------------------------------------------
# Close the login file
#------------------------------------------------------------------------
close CHECKINLOG;


#------------------------------------------------------------------------
# Send to reception
#------------------------------------------------------------------------
sub sendToReception
{
    #$message_bgcolor 		= $darkgreen;
    $message_txtcolor 		= $white;
    $MainMessage_fr 		= "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en 		= "Please go to the reception";
    $subMessage_fr 		= "Impossible de vous enregistrer en ce moment";  
    $subMessage_en 		= "Unable to check you in at this time";  
    $middleMessage_fr 		= "<b></b>";
    $middleMessage_en 		= "<b></b>";
    $arrows 			= 1;
    $DestinationWaitingRoom	= "reception";
    $middleMessage_image 	= "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image 	= "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message 		= "Problem detected - sending to reception - $PatientId, $location, $subMessage_en";
    

    # set the resulting webpage to reload to the default page after 20 seconds so that the next patient can check in
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$ReloadFinal\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    # Report outcome of check in attempt to the user
    printUI(1);

} # end of sendToReception

#------------------------------------------------------------------------
# Start the webpage feedback
#------------------------------------------------------------------------
sub printUI
{
  # reassign the arguments
  #my ($form) = @_;

  my $verboseColor;
  if($verbose)
  {
    $verboseColor = "black";
  }
  else
  {
    $verboseColor = "white";
  }

  # General stuff
  my $whitespace 		= "<font color = \"white\">space</font>";

  my $whitespace_verbose 	= "<a href=\"$checkin_script?verbose=$verboselink\"><font color = \"$verboseColor\">space</font></a>";
  my $whitespace_DRC_1		= "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_1\"><font color = \"$verboseColor\">DRC_1</font></a>";
  my $whitespace_DRC_2		= "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_2\"><font color = \"$verboseColor\">DRC_2</font></a>";
  my $whitespace_DRC_3		= "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_3\"><font color = \"$verboseColor\">DRC_3</font></a>";
  my $whitespace_DS1_1		= "<a href=\"$checkin_script?verbose=$verboselink&location=DS1_1\"><font color = \"$verboseColor\">DS1_1</font></a>";
  my $whitespace_DS1_2		= "<a href=\"$checkin_script?verbose=$verboselink&location=DS1_2\"><font color = \"$verboseColor\">DS1_2</font></a>";
  my $whitespace_Ortho_1		= "<a href=\"$checkin_script?verbose=$verboselink&location=Ortho_1\"><font color = \"$verboseColor\">Ortho_1</font></a>";
  my $whitespace_Ortho_2		= "<a href=\"$checkin_script?verbose=$verboselink&location=Ortho_2\"><font color = \"$verboseColor\">Ortho_2</font></a>";
  my $whitespace_ReceptionRC = "<a href=\"$checkin_script?verbose=$verboselink&location=ReceptionRC\"><font color = \"$verboseColor\">Reception - RC</font></a>";
  my $whitespace_ReceptionS1 = "<a href=\"$checkin_script?verbose=$verboselink&location=ReceptionS1\"><font color = \"$verboseColor\">Reception - S1</font></a>";
  my $whitespace_ReceptionOrtho = "<a href=\"$checkin_script?verbose=$verboselink&location=ReceptionOrtho\"><font color = \"$verboseColor\">Reception - Ortho</font></a>";
  

  #------------------------------------------------------------------------
  # Header
  #------------------------------------------------------------------------
  print "
  <head>
    <title>Patient Check In</title>
  <script>
  	window.onload = function() {
  	document.getElementById(\"CheckinBox\").focus();
	};
  </script>
  $reload
  </head>
  <body style=\"color: rgb(0, 0, 0); background-color: $gray; font-size:18px; font-family: Helvetica,Arial,sans-serif;\" link=\"#0000ee\" vlink=\"#551a8b\" alink=\"#ee0000\" >
  <center>
  ";

  #------------------------------------------------------------------------
  # Prepare table
  #------------------------------------------------------------------------
  print "
  <table style=\"text-align: left; width: 1000px; background-color: rgb(255, 255, 255); font-size:24px;\" border=\"$border\" cellpadding=\"2\" cellspacing=\"10\">
  <tbody>
  ";

  #------------------------------------------------------------------------
  # Header - allow for a transparent link for experts to get verbose mode in the top left corner
  #------------------------------------------------------------------------
  print "
    <tr><td colspan=\"5\"><img src=\"$images/topline.png\"</td></tr>
    <tr style=\"font-family: Helvetica,Arial,sans-serif;\">
 	<td>$whitespace_DRC_1</td>
	<td style=\"vertical-align: center; align: center; background-color: rgb(255, 255, 255);\">
	  <center>$whitespace_Ortho_1<img src=\"$images/logo.png\" alt=\"MUHC logo\" border=\"$border\">$whitespace_Ortho_2 <br> <font size=\"1\"> $whitespace_ReceptionRC $whitespace_ReceptionS1 $whitespace_ReceptionOrtho</font></center>
	</td>
 	<td>$location_image</td>
    </tr>
  ";

  #------------------------------------------------------------------------
  # Message French
  #------------------------------------------------------------------------
  print "
      <tr style=\"font-family: Helvetica,Arial,sans-serif;\">
 	<td>$whitespace_DRC_2</td>
	<td style=\"vertical-align: center; background-color: $message_bgcolor;\">
	  <span style=\"font-weight: bold; color: $message_txtcolor; font-size:60px;\">
			<center>$MainMessage_fr</center>
	  </span>
	</td>
	<td>$whitespace_DS1_1</td>
      </tr>
  ";

  #------------------------------------------------------------------------
  # Appointment details French 
  #------------------------------------------------------------------------
  print "
      <tr style=\"font-family: Helvetica,Arial,sans-serif;\">
   	<td>$whitespace_DRC_3</td>
  	<td style=\"vertical-align: center; background-color: $middleMessage_bgcolor;\">
	  <span style=\"color: $middleMessage_txtcolor;font-size:30px;\">
			$subMessage_fr	
	  </span><br>
	  <!--<img src=\"$images/line_horizontal.png\">-->
	</td>
	<td>$whitespace_DS1_2</td>
      </tr>
  ";

  ###################################################################
  # Patient Directions
  ###################################################################
  # Figure out the direction to send the patient (right, left, stay put) based on his/her
  # current location and waiting room destination
  my $direction = "hereDefault"; # default is here 

  # location = DRC_1
  if($location eq "DRC_1")
  {
    $direction = "right" 	if $DestinationWaitingRoom eq "reception";
    $direction = "hereRC" 	if $DestinationWaitingRoom eq "DRC";
    $direction = "down" 	if $DestinationWaitingRoom eq "DS1";

    $direction = "down" 	if $DestinationWaitingRoom eq "STX_1";
    $direction = "down" 	if $DestinationWaitingRoom eq "STX_2";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_3";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_4";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_5";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_6";
    $direction = "hereRC" 	if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DRC_2 
  if($location eq "DRC_2")
  {
    $direction = "left" 	if $DestinationWaitingRoom eq "reception";
    $direction = "hereRC" 	if $DestinationWaitingRoom eq "DRC";
    $direction = "down" 	if $DestinationWaitingRoom eq "DS1";

    $direction = "down" 	if $DestinationWaitingRoom eq "STX_1";
    $direction = "down" 	if $DestinationWaitingRoom eq "STX_2";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_3";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_4";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_5";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_6";
    $direction = "up_left" 	if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DRC_3 
  if($location eq "DRC_3")
  {
    $direction = "left" 	if $DestinationWaitingRoom eq "reception";
    $direction = "left" 	if $DestinationWaitingRoom eq "DRC";
    $direction = "down" 	if $DestinationWaitingRoom eq "DS1";

    $direction = "down" 	if $DestinationWaitingRoom eq "STX_1";
    $direction = "down" 	if $DestinationWaitingRoom eq "STX_2";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_3";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_4";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_5";
    $direction = "down" 	if $DestinationWaitingRoom eq "TB_6";
    $direction = "right" 	if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DS1_1 
  if($location eq "DS1_1")
  {
    $direction = "up" 		if $DestinationWaitingRoom eq "DRC";
    $direction = "here" 	if $DestinationWaitingRoom eq "DS1";
    $direction = "right" 	if $DestinationWaitingRoom eq "reception";
    $direction = "here" 	if $DestinationWaitingRoom eq "STX_1";
    $direction = "here" 	if $DestinationWaitingRoom eq "STX_2";
    $direction = "here" 	if $DestinationWaitingRoom eq "TB_3";
    $direction = "here" 	if $DestinationWaitingRoom eq "TB_4";
    $direction = "here" 	if $DestinationWaitingRoom eq "TB_5";
    $direction = "here" 	if $DestinationWaitingRoom eq "TB_6";
    $direction = "up" 		if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DS1_2 
  if($location eq "DS1_2")
  {
    $direction = "up" 		if $DestinationWaitingRoom eq "DRC";
    $direction = "left" 	if $DestinationWaitingRoom eq "DS1";
    $direction = "left" 	if $DestinationWaitingRoom eq "reception";
    $direction = "left" 	if $DestinationWaitingRoom eq "STX_1";
    $direction = "left" 	if $DestinationWaitingRoom eq "STX_2";
    $direction = "left" 	if $DestinationWaitingRoom eq "TB_3";
    $direction = "left" 	if $DestinationWaitingRoom eq "TB_4";
    $direction = "left" 	if $DestinationWaitingRoom eq "TB_5";
    $direction = "left" 	if $DestinationWaitingRoom eq "TB_6";
    $direction = "up" 		if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = Ortho_1 (outside of Ortho clinic) 
  if($location eq "Ortho_1")
  {
    $direction = "leftOrtho" 	if $DestinationWaitingRoom eq "OrthoWaitRoom";
    $direction = "leftOrtho" 	if $DestinationWaitingRoom eq "reception";
  }

  # location = Ortho_2 (inside Ortho clinic) 
  if($location eq "Ortho_2")
  {
    $direction = "hereOrtho" 	if $DestinationWaitingRoom eq "OrthoWaitRoom";
    $direction = "leftOrtho" 	if $DestinationWaitingRoom eq "reception";
    $direction = "rightOrtho" 	if $DestinationWaitingRoom eq "RadiologyMGH";
    $direction = "rightOrtho" 	if $DestinationWaitingRoom eq "MGH_Admissions";
  }
  
  print "Location: $location, Waiting Room destination: $DestinationWaitingRoom, Direction: $direction<br>" if $verbose;
  my $direction_image;
  $direction_image = "<img width=\"200\" src=\"$images/arrow_$direction.png\">";

  $direction_image = "<img width=\"80\" src=\"$images/arrow_$direction.png\">" if $direction eq "hereDefault";

  print "
      <tr style=\"font-family: Helvetica,Arial,sans-serif;\" align=\"center\">
 	<td colspan=\"3\" style=\"vertical-align: center; background-color: $middleMessage_bgcolor;\">
 	  <table border=\"0\" cellpadding=\"20\">
	 <tr>
 	        <td rowspan=\"1\">$shortline</td>
		<td valign=\"center\">";

  print "	  $direction_image" if $arrows; 
  print "	</td>
		<td valign=\"center\">
	  	  <span style=\"font-weight: bold; color: $middleMessage_txtcolor; font-size:25px;\">
		  <center>
		  $middleMessage_fr
		  <p>
		  $middleMessage_image
	   	  <p>
		  $middleMessage_en
		  </center>
	  	  </span>
		</td>
		<td valign=\"center\">" ;

  print "	  $direction_image" if $arrows;
  print "	</td>
 	        <td rowspan=\"1\">$shortline</td>
	</tr>
	  </table>
	<!---<font style=\"color: $white;\">--<font><br>	-->
	</td>
      </tr>
  ";

  #------------------------------------------------------------------------
  # Main Message English 
  #------------------------------------------------------------------------
  print "
      <tr style=\"font-family: Helvetica,Arial,sans-serif;\">
 	<td></td>
	<td style=\"vertical-align: center; background-color: $message_bgcolor;\">
	  <span style=\"font-weight: bold; color: $message_txtcolor; font-size:60px;\">
			<center>$MainMessage_en</center>
	  </span>
	</td>
 	<td></td>
      </tr>
  ";

  #------------------------------------------------------------------------
  # Appointment English 
  #------------------------------------------------------------------------
  print "
      <tr style=\"font-family: Helvetica,Arial,sans-serif;\">
 	<td></td>
	<td style=\"vertical-align: center; background-color: $middleMessage_bgcolor;\">
	  <span style=\"color: $middleMessage_txtcolor;font-size:30px;\">
			$subMessage_en
	  </span>
	  <p>
	</td>
 	<td></td>
      </tr>
  ";

  #------------------------------------------------------------------------
  # Form
  #------------------------------------------------------------------------
  print "
  <tr><td colspan=\"3\">
  <center>
  <form name=\"demographicDiagnostic\" action=\"$checkin_script\"> 
  <input id=\"CheckinBox\" type=\"text\" name=\"PatientId\" autofocus>
  <input type=\"hidden\" name=\"location\" value=\"$location\">
  <input type=\"hidden\" name=\"verbose\" value=\"$verbose\">
  </form>
  </center>
  </td></tr>
  ";

  #------------------------------------------------------------------------
  # Table end
  #------------------------------------------------------------------------
  print "
    </tbody>
    </table>
  ";

  #------------------------------------------------------------------------
  # End Page 
  #------------------------------------------------------------------------
  print "
  </center>
  </body>
  </html>
  ";

  #------------------------------------------------------------------------
  # Log the details of the patient interaction
  #------------------------------------------------------------------------
  my ($year_today,$month_today,$day_today, $hour_today,$min_today,$sec_today) = Today_and_Now();
  my $now = "$month_today/$day_today/$year_today $hour_today:$min_today:$sec_today";
  #print CHECKINLOG "$now, location: $location, destination: $DestinationWaitingRoom, direction: $direction, main: $MainMessage_en, sub: $subMessage_en, reload: $reload\n\n" if $logging;
  print CHECKINLOG "###$now, $log_message, $DestinationWaitingRoom, $direction, $subMessage_en\n\n" if $logging;
  print "$now, $log_message, $DestinationWaitingRoom, $direction, $subMessage_en\n\n" if $verbose;


}# end of UI

#------------------------------------------------------------------------
# Find this patient - look in Aria first and then Medivisit (MySQL) 
#------------------------------------------------------------------------
sub findPatient 
{
  print "Retrieving data for patient ID Patient$hospitalAria = $PatientId in Aria and Patient$hospitalMV = $PatientId in MV<br>" if $verbose;

  #------------------------------------------------------------------------
  # Hospital IDs are numeric only. If there are letters in the ID then it is
  # the SSN, so search by SSN
  #------------------------------------------------------------------------
  my $PatientIdentifier = "";
  my $PatientIdentifierMV = "";
  if($PatientId =~ /[a-zA-Z]/)
  {
    # The SSN is stored in Aria in the form LASFYYMMDDXX YYYYMM
    # However, the scanned barcode only contains LASFYYMMDDX
    print "Patient SSN: $PatientId<br>" if $verbose;    

    $PatientId = substr($PatientId,0,12);
    print "Patient SSN, after truncation: $PatientId<br>" if $verbose;  

    $PatientIdentifier = "Patient.SSN LIKE \'$PatientId %\'" ;
    $PatientIdentifierMV = "Patient.SSN = \'$PatientId\'"; 
  }
  else
  {
    print "Patient ID: $PatientId<br>" if $verbose;    
    $PatientIdentifier = "Patient.PatientId$hospitalAria = \'$PatientId\'" ;
    $PatientIdentifierMV = "Patient.PatientId$hospitalMV = \'$PatientId\'"; 
  }
 
  ##############################################################################################
  #					Aria						       #
  ##############################################################################################
  #------------------------------------------------------------------------
  # Connect to the Aria database 
  #------------------------------------------------------------------------
  # Embed the database info in the connect command since otherwise it
  # doesn't seem to work (for an unknown reason...)
  my $dbh;
  
  # only use Aria if specified
  if($useAria)
  {
    $dbh =  LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database on 1st attempt \n\n";
      #or die "Couldn't connect to database: " . DBI->errstr;

    # if it didn't work, try again
    if(!$dbh)
    {
      $dbh =  LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database on 2nd attempt \n\n";
    }

    # if it didn't work, try again
    if(!$dbh)
    {
      $dbh = LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database on 3rd attempt \n\n";
    }

    # if it didn't work, try again
    if(!$dbh)
    {
      $dbh =  LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database on 4th attempt \n\n";
    }

    # if it didn't work, try again
    if(!$dbh)
    {
      $dbh =  LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database on 5th attempt \n\n";
    }
  } # end of useAria

  #============================================================================================
  # Retrieve data 
  #============================================================================================
  my $sqlID;
  my $PatientSer 	= "NULL"; # PatientSer is NULL until filled
  my $PatientSerNum 	= "NULL"; # PatientSer is NULL until filled
  my $PatientLastName;
  my $PatientFirstName;
  my $PatientSSN;
  my $PatientSSN_expiry; # defining here but ignoring SSN expiry data in Aria as not sure that it is always in the same format

  if($useAria)
  {
	  $sqlID = "SELECT DISTINCT 
			Patient.LastName,
			Patient.FirstName,
			Patient.PatientSer,
			Patient.SSN
		  FROM  variansystem.dbo.Patient Patient
		  WHERE    
		  $PatientIdentifier
	  ";

	  # print the SQL query for debugging
	  print "SQL: $sqlID<br><p>\n" if $verbose;

	  # prepare and check the query for the database
	  my $queryID= $dbh->prepare($sqlID)
	    or print CHECKINLOG "###$now, $location Couldn't prepare Aria sqlID statement: $dbh->errstr\n\n";

	  # submit queryID to server
	  $queryID->execute()
	    or print CHECKINLOG "###$now, $location Couldn't execute Aria sqlID statement: $dbh->errstr\n\n";

	  my @data = $queryID->fetchrow_array();

	  $PatientLastName 	= $data[0];
	  $PatientFirstName	= $data[1];
	  $PatientSer		= $data[2] if $data[2];
	  $PatientSSN		= $data[3];
	  
	  # separate out the expiry date from the SSN
	  # ignoring for now as per above
	  my @split_SSN = split(/\s+/, $PatientSSN);   
	  $PatientSSN_expiry = $split_SSN[1];
	  $PatientSSN = $split_SSN[0];
  } # end of useAria

  ##############################################################################################
  #					MySQL/Medivisit					       #
  ##############################################################################################
  #------------------------------------------------------------------------
  # Database initialisation stuff
  #------------------------------------------------------------------------
  my $dbh_mysql = LoadConfigs::GetDatabaseConnection("ORMS") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database: \n\n";

  $sqlID = "SELECT DISTINCT 
		Patient.LastName,
		Patient.FirstName,
		Patient.PatientSerNum,
		Patient.SSN,
		Patient.SSNExpDate
          FROM  Patient Patient
          WHERE    
	  $PatientIdentifierMV
  ";
  print "SQLID: $sqlID>br>" if $verbose;

  my $query= $dbh_mysql->prepare($sqlID) 
    #or die "Couldn't prepare MySQL sqlID statement: " . $dbh_mysql->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't prepre MySQL sqlID statement: " . $dbh_mysql->errstr . "\n\n";

  $query->execute() 
     #or die "Couldn't execute MySQL sqlID statement: " . $query->errstr;
     or print CHECKINLOG "###$now, $location ERROR - Couldn't execute MySQL sqlID statement: " . $query->errstr . "\n\n";

  my @data = $query->fetchrow_array();  

  # grab data from MySQL 
  $PatientLastName 	= $data[0] if $data[0];
  $PatientFirstName	= $data[1] if $data[1];
  $PatientSerNum	= $data[2] if $data[2];
  $PatientSSN		= $data[3] if $data[3];
  $PatientSSN_expiry	= $data[4] if $data[4];

  print "Patient LastName: $PatientLastName <br>" if $verbose;
  print "Patient FirstName: $PatientFirstName <br>" if $verbose;
  print "Patient SSN in MV : $PatientSSN<br>" if $verbose;
  print "Patient SSN expiry in MV: $PatientSSN_expiry<br>" if $verbose;
  print "PatientSer (Aria) in findPatient: $PatientSer <br>" if $verbose;
  print "PatientSerNum (Medivisit) in findPatient: $PatientSerNum <br>" if $verbose;

  #######################################################################
  # Check that the SSN has not expired - use the ADT Webservice to find
  # the correct expiration date based on the RAMQ number. The expiration 
  # date in Aria and/or Medivisit might not be correct if the patient has
  # just updated it at Admissions so we need to query the ADT ourselves
  # directly
  #######################################################################
  my $RAMQCardExpired;

  #call the hospital ADT to check if the ramq is expired
  my $ramqInfo = HospitalADT->getRamqInformation($PatientSSN);

  if($verbose)
  {
	print "Ramq function status: $ramqInfo->{'Status'}<br>";
	print "Ramq function message: $ramqInfo->{'Message'}<br>";
  }

  #if an error is produced, we count the ramq as valid since the patient, probably doesn't have a ramq yet
  if($ramqInfo->{'Status'} =~ /Valid|Error/)
  {
	#likewise, if the patient has no MRN, they probably don't have a ramq
	if(!$ramqInfo->{'Mrns'} or $ramqInfo->{'Mrns'} =~ /$mrnType/)
	{
	  $RAMQCardExpired = 0;

	  #update the WRM db with the valid ramq expiration date if the current one in the WRM db is expired
	  my $result = HospitalADT->updateRamqInWRM($PatientSSN);
	  print "$result<br>" if $verbose;
	}
	else
	{
		$RAMQCardExpired = 1;	
	}
  }
  else
  {
	$RAMQCardExpired = 1;
  }
  print "Is ramq expired? : $RAMQCardExpired<br>" if $verbose;


  #######################################################################

  # Set the patient's display name for the screen using the SSN
  my $PatientSSNLetters = substr($PatientSSN,0,3);
  my $PatientDAYOFBIRTH = substr($PatientSSN,8,2);
  my $PatientDisplayName = "$PatientFirstName $PatientSSNLetters****";

  #######################################################################
  # Exit function
  #######################################################################
  print "Returning for findPatient: ($PatientLastName,$PatientFirstName,$PatientSer,$PatientSerNum,$PatientDisplayName,$RAMQCardExpired)<br>" if $verbose;
  my @returnValue = ($PatientLastName,$PatientFirstName,$PatientSer,$PatientSerNum,$PatientDisplayName,$RAMQCardExpired);
  return @returnValue;
}

#------------------------------------------------------------------------
# Attempt to checkin this patient 
#------------------------------------------------------------------------
sub CheckinPatient 
{
  # reassign the arguments
  my @returnValue;
  my @returnValueAria;
  my @returnValueMedivisit;
  my ($PatientSer,$PatientSerNum,$startOfToday,$startOfToday_mysql,$endOfToday,$endOfToday_mysql) = @_;

  print "Checking in PatientSer  $PatientSer<br>" if $verbose;
  print "Checking in PatientSerNum  $PatientSerNum<br>" if $verbose;

  my $PhotoOk = 1; # ok by default - eg if not an Aria patient it will be ok
  my $PilotOk = 1; # ok by default - unless the patient has a non-pilot medivisit appointment it should be ok

  ##############################################################################################
  #					Aria						       #
  ##############################################################################################
  my $dbh;
  if($useAria)
  {
	  $dbh =  LoadConfigs::GetDatabaseConnection("ARIA") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database: \n\n";
  }

  ##############################################################################################
  #					MySQL/Medivisit					       #
  ##############################################################################################
  my $dbh_mysql = LoadConfigs::GetDatabaseConnection("ORMS") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database: \n\n";

  #============================================================================================
  # Determine the patient's ***NEXT*** appointment TODAY Get in ascending order so the very next 
  # appointment is picked of the top
  #============================================================================================

  ##############################################################################################
  #					Aria appointment search				       #
  ##############################################################################################
  my @PatientId;
  my @PatientFirstName;
  my @PatientLastName;
  my @ScheduledStartTime;
  my @ApptDescription;
  my @ResourceSer;
  my @ScheduledActivitySer;
  my @AuxiliaryId;
  my @ResourceType;
  my @AptTimeSinceMidnight;
  my @ScheduledStartTime_en;
  my @ScheduledStartTime_fr;
  my @Aria_Appt_type_en;
  my @Aria_Appt_type_fr;
  my @Aria_MachineId;
  my @AptTimeHour;
  my @Aria_AMorPM;
  my $numAriaAppts = 0;

  if($useAria)
  {
	  my $sqlAppt;
	  
	  # We need a union on the query as the appointment is either with an auxiliary or a doctor or a resource 
	  $sqlAppt = "
		  SELECT DISTINCT 
			Patient.PatientId$hospitalAria,
			Patient.FirstName,
			Patient.LastName,
			ScheduledActivity.ScheduledStartTime,
			vv_ActivityLng.Expression1,
			ResourceActivity.ResourceSer,	
			ScheduledActivity.ScheduledActivitySer,
			Auxiliary.AuxiliaryId,
			Resource.ResourceType,	
			DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight, 
			DATEPART(hh,ScheduledActivity.ScheduledStartTime)
		  FROM  
			variansystem.dbo.Patient Patient,
			variansystem.dbo.ScheduledActivity ScheduledActivity,
			variansystem.dbo.ActivityInstance ActivityInstance,
			variansystem.dbo.Activity Activity,
			variansystem.dbo.vv_ActivityLng vv_ActivityLng,
			variansystem.dbo.ResourceActivity ResourceActivity,
			variansystem.dbo.Auxiliary Auxiliary, 
			variansystem.dbo.Resource Resource
		  WHERE 
			( ScheduledActivity.ScheduledStartTime 		> \'$startOfToday\' )    
		    AND	( ScheduledActivity.ScheduledStartTime 		< \'$endOfToday\' )    
		    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
		    AND ( Patient.PatientSer 				= $PatientSer)           
		    AND ( ScheduledActivity.ScheduledActivityCode 	= \'Open\')
		    AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
		    AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
		    AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
		    AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
		    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FIRST CONSULT\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FIRST CONSULT\')
		    AND ( 
			  vv_ActivityLng.Expression1 			LIKE \'%.EB%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%.BXC%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%F-U %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW UP %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW-UP%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Transfusion%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Injection%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Nursing Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Hydration%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CONSULT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Daily Rx%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%INTRA%\'
			)
		    AND  ResourceActivity.ResourceSer = Auxiliary.ResourceSer 
		    AND  Resource.ResourceSer = Auxiliary.ResourceSer 
		UNION
		  SELECT DISTINCT
			Patient.PatientId$hospitalAria,
			Patient.FirstName,
			Patient.LastName,
			ScheduledActivity.ScheduledStartTime,
			vv_ActivityLng.Expression1,
			ResourceActivity.ResourceSer,	
			ScheduledActivity.ScheduledActivitySer,
			Doctor.DoctorId,	
			Resource.ResourceType,	
			DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight,
			DATEPART(hh,ScheduledActivity.ScheduledStartTime)
		  FROM  
			variansystem.dbo.Patient Patient,
			variansystem.dbo.ScheduledActivity ScheduledActivity,
			variansystem.dbo.ActivityInstance ActivityInstance,
			variansystem.dbo.Activity Activity,
			variansystem.dbo.vv_ActivityLng vv_ActivityLng,
			variansystem.dbo.ResourceActivity ResourceActivity,
			variansystem.dbo.Doctor Doctor,
			variansystem.dbo.Resource Resource
		  WHERE 
			( ScheduledActivity.ScheduledStartTime 		> \'$startOfToday\' )    
		    AND	( ScheduledActivity.ScheduledStartTime 		< \'$endOfToday\' )    
		    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
		    AND ( Patient.PatientSer 				= $PatientSer)           
		    AND ( ScheduledActivity.ScheduledActivityCode 	= \'Open\')
		    AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
		    AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
		    AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
		    AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
		    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FIRST CONSULT\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FIRST CONSULT\')
		    AND ( 
			  vv_ActivityLng.Expression1 			LIKE \'%.EB%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%.BXC%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%F-U %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW UP %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW-UP%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Transfusion%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Injection%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Nursing Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Hydration%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CONSULT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%INTRA%\'	
			)
		    AND  ResourceActivity.ResourceSer = Doctor.ResourceSer 
		    AND  Resource.ResourceSer = Doctor.ResourceSer 
		UNION
		  SELECT DISTINCT 
			Patient.PatientId$hospitalAria,
			Patient.FirstName,
			Patient.LastName,
			ScheduledActivity.ScheduledStartTime,
			vv_ActivityLng.Expression1,
			ResourceActivity.ResourceSer,	
			ScheduledActivity.ScheduledActivitySer,
			CONVERT(varchar(30), Resource.ResourceSer),
			Resource.ResourceType,	
			DATEDIFF(n,'$startOfToday',ScheduledActivity.ScheduledStartTime) AS AptTimeSinceMidnight, 
			DATEPART(hh,ScheduledActivity.ScheduledStartTime)
		  FROM  
			variansystem.dbo.Patient Patient,
			variansystem.dbo.ScheduledActivity ScheduledActivity,
			variansystem.dbo.ActivityInstance ActivityInstance,
			variansystem.dbo.Activity Activity,
			variansystem.dbo.vv_ActivityLng vv_ActivityLng,
			variansystem.dbo.ResourceActivity ResourceActivity,
			variansystem.dbo.Resource Resource
		  WHERE 
			( ScheduledActivity.ScheduledStartTime 		> \'$startOfToday\' )    
		    AND	( ScheduledActivity.ScheduledStartTime 		< \'$endOfToday\' )    
		    AND ( ScheduledActivity.PatientSer 			= Patient.PatientSer)           
		    AND ( Patient.PatientSer 				= $PatientSer)           
		    AND ( ScheduledActivity.ScheduledActivityCode 	= \'Open\')
		    AND ( ResourceActivity.ScheduledActivitySer 	= ScheduledActivity.ScheduledActivitySer )
		    AND ( ScheduledActivity.ActivityInstanceSer 	= ActivityInstance.ActivityInstanceSer)
		    AND ( ActivityInstance.ActivitySer 			= Activity.ActivitySer)
		    AND ( Activity.ActivityCode 			= vv_ActivityLng.LookupValue)
		    AND ( Patient.PatientSer 				= ScheduledActivity.PatientSer)           
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FOLLOW UP\')
		    AND ( vv_ActivityLng.Expression1 			!= \'NUTRITION FIRST CONSULT\')
		    AND ( vv_ActivityLng.Expression1 			!= \'FIRST CONSULT\')
		    AND ( 
			  vv_ActivityLng.Expression1 			LIKE \'%.EB%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%.BXC%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%F-U %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW UP %\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%FOLLOW-UP%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Transfusion%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Injection%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Nursing Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Hydration%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%Consult%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%CONSULT%\'
			 OR vv_ActivityLng.Expression1 			LIKE \'%INTRA%\' 
			)
		    AND  ResourceActivity.ResourceSer = Resource.ResourceSer
		  ORDER BY ScheduledActivity.ScheduledStartTime ASC 
		";

	  # print the SQL query for debugging
	  print "SQL: $sqlAppt<br><p>\n" if $verbose;

	  # prepare and check the query for the database
	  my $queryAppt= $dbh->prepare($sqlAppt)
	    #or die "Couldn't prepare Aria sqlAppt statement: " . $dbh->errstr;
	    or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare Aria sqlAppt statement: " . $dbh->errstr . "\n\n";

	  # submit queryAppt to server
	  $queryAppt->execute()
	    #or die "Couldn't execute Aria sqlAppt statement: " . $queryAppt->errstr;
	    or print CHECKINLOG "###$now, $location ERROR - Couldn't execute Aria sqlAppt statement: " . $queryAppt->errstr . "\n\n";

	  print "<br>-------------Aria Appointment Search---------------------------<br>" if $verbose;

	  $AptTimeSinceMidnight[0] = 999999999999999999999; # fail safe
	  while(my @data = $queryAppt->fetchrow_array())
	  {
	    print "<br>-------------Aria Appointment Search---------------------------<br>" if $verbose;
	    print "Data: @data<br>" if $verbose;

	    $PatientId[$numAriaAppts]		= $data[0];
	    $PatientFirstName[$numAriaAppts]	= $data[1];
	    $PatientLastName[$numAriaAppts]	= $data[2];
	    $ScheduledStartTime[$numAriaAppts]	= $data[3];
	    $ApptDescription[$numAriaAppts]	= $data[4];
	    $ResourceSer[$numAriaAppts]		= $data[5];
	    $ScheduledActivitySer[$numAriaAppts]= $data[6];
	    $AuxiliaryId[$numAriaAppts]		= $data[7];
	    $ResourceType[$numAriaAppts]	= $data[8];
	    $AptTimeSinceMidnight[$numAriaAppts]= $data[9];
	    $AptTimeHour[$numAriaAppts]		= $data[10];
	   
	    # appointment AM or PM
	    if($AptTimeHour[$numAriaAppts] >= 13)
	    {
	      $Aria_AMorPM[$numAriaAppts] = "PM";
	    }
	    else
	    {
	      $Aria_AMorPM[$numAriaAppts] = "AM";
	    }

	    print "Patient LastName: $PatientLastName[$numAriaAppts] <br>" 	if $verbose;
	    print "ScheduledStartTime: $ScheduledStartTime[$numAriaAppts] <br>" if $verbose;
	    print "ApptDescription: $ApptDescription[$numAriaAppts]<br>" 	if $verbose;
	    print "ResourceSer: $ResourceSer[$numAriaAppts]<br>" 		if $verbose;
	    print "AuxiliaryId: $AuxiliaryId[$numAriaAppts]<br>" 		if $verbose;
	    print "ResourceType: $ResourceType[$numAriaAppts]<br>" 		if $verbose;
	    print "AptTimeSinceMidnight: $AptTimeSinceMidnight[$numAriaAppts]<br>" if $verbose;
	    print "AptTimeHour: $AptTimeHour[$numAriaAppts]<br>" 		if $verbose;
    	    print "Earliest Aria AptTimeSinceMidnight: $AptTimeSinceMidnight[0]<br>" if $verbose;

	    # Parse the start datetime and extract just the time
	    # In the format: Mar 19 2014 08:00PM 
	    my @split_ScheduledStartDateTime 	= split(/\s+/, $ScheduledStartTime[$numAriaAppts]);   

	    my $ScheduledStartTime		= $split_ScheduledStartDateTime[3];
	    print "ScheduledStartTime: $ScheduledStartTime<br>" if $verbose;

	    my @split_ScheduledStartTime 	= split(/:/, $ScheduledStartTime);   
	  

	    my $hour_ScheduledStartTime		= $split_ScheduledStartTime[0];
	    print "hour_ScheduledStartTime: $hour_ScheduledStartTime<br>" if $verbose;

	    my $minAMPM_ScheduledStartTime	= $split_ScheduledStartTime[1];
	    my $min_ScheduledStartTime		= substr($minAMPM_ScheduledStartTime,0,2);
	    my $AMPM_ScheduledStartTime		= substr($minAMPM_ScheduledStartTime,2,2);

	    #print "ScheduledStartTime: $ScheduledStartTime<br>" if $verbose;
	    #print "hour_ScheduledStartTime: $hour_ScheduledStartTime<br>" if $verbose;
	    #print "min_ScheduledStartTime: $min_ScheduledStartTime<br>" if $verbose;
	    #print "minAMPM_ScheduledStartTime: $minAMPM_ScheduledStartTime<br>" if $verbose;
	    #print "AMPM_ScheduledStartTime: $AMPM_ScheduledStartTime<br>" if $verbose;

	    $ScheduledStartTime_en[$numAriaAppts] = "$hour_ScheduledStartTime:$min_ScheduledStartTime $AMPM_ScheduledStartTime";

	    # Fix the french time since it is 24 hour
	    if($AMPM_ScheduledStartTime eq "PM" && $hour_ScheduledStartTime < 12)
	    {
	      print "fixing French hour $hour_ScheduledStartTime + 12<br>" if $verbose;
	      my $hour_ScheduledStartTime_fr = $hour_ScheduledStartTime + 12;
	      print "$hour_ScheduledStartTime_fr = $hour_ScheduledStartTime + 12<br>" if $verbose;;
	      $ScheduledStartTime_fr[$numAriaAppts] = "$hour_ScheduledStartTime_fr h $min_ScheduledStartTime";

	      print "French time: $ScheduledStartTime_fr[$numAriaAppts]<br>" if $verbose;
	    }
	    else
	    {
	      $ScheduledStartTime_fr[$numAriaAppts] = "$hour_ScheduledStartTime h $min_ScheduledStartTime";
	    }

	    #------------------------------------------------------------------------
	    # Figure out the type of Aria appointment here
	    #------------------------------------------------------------------------
	    $Aria_Appt_type_en[$numAriaAppts] = "Appointment";
	    $Aria_Appt_type_fr[$numAriaAppts] = "rendez-vous";
	  
	    # Radiotherapy 	- if contains .EB 
	    $Aria_Appt_type_en[$numAriaAppts] = "Treatment" 			if($ApptDescription[$numAriaAppts]=~ m/.EBC/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Traitement" 			if($ApptDescription[$numAriaAppts]=~ m/.EBC/i);
	  
	    $Aria_Appt_type_en[$numAriaAppts] = "Treatment" 			if($ApptDescription[$numAriaAppts]=~ m/.EBP/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Traitement" 			if($ApptDescription[$numAriaAppts]=~ m/.EBP/i);
	  
	    $Aria_Appt_type_en[$numAriaAppts] = "Treatment" 			if($ApptDescription[$numAriaAppts]=~ m/.EBM/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Traitement" 			if($ApptDescription[$numAriaAppts]=~ m/.EBM/i);
	  
	    print "$ApptDescription[$numAriaAppts]: ApptDescription[$numAriaAppts]<br>" if $verbose;
	  
	    # CT Simulation 	- if contains CT
	    $Aria_Appt_type_en[$numAriaAppts] = "CT Simulation" 		if($ApptDescription[$numAriaAppts]=~ m/CT/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Simulation au scan" 		if($ApptDescription[$numAriaAppts]=~ m/CT/i);
	  
	    # Brachytherapy	- if contains .BXC 
	    $Aria_Appt_type_en[$numAriaAppts] = "Brachytherapy" 		if($ApptDescription[$numAriaAppts]=~ m/.BXC/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Curieth&eacute;rapie" 	if($ApptDescription[$numAriaAppts]=~ m/.BXC/i);
	  
	    # Follow-up 		- if contains FOLLOW
	    $Aria_Appt_type_en[$numAriaAppts] = "Follow-up" 		if($ApptDescription[$numAriaAppts]=~ m/FOLLOW/i || $ApptDescription[$numAriaAppts]=~ m/F-U/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Suivi" 			if($ApptDescription[$numAriaAppts]=~ m/FOLLOW/i || $ApptDescription[$numAriaAppts]=~ m/F-U/i);
	  
	    # Consultation 	- if contains CONSULT
	    $Aria_Appt_type_en[$numAriaAppts] = "Consultation" 		if($ApptDescription[$numAriaAppts]=~ m/CONSULT/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Consultation" 		if($ApptDescription[$numAriaAppts]=~ m/CONSULT/i);
	  
	    # Injection 	- if contains Injection 
	    $Aria_Appt_type_en[$numAriaAppts] = "Injection - Nursing" 	if($ApptDescription[$numAriaAppts]=~ m/Injection/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Injection - Soins infirmiers" if($ApptDescription[$numAriaAppts]=~ m/Injection/i);
	  
	    # Transfusion - if contains Transfusion 
	    $Aria_Appt_type_en[$numAriaAppts] = "Transfusion - Nursing" 	if($ApptDescription[$numAriaAppts]=~ m/Transfusion/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Transfusion - Soins infirmiers" if($ApptDescription[$numAriaAppts]=~ m/Transfusion/i);
	  
	    # Nursing consult- if contains Nursing Consult 
	    $Aria_Appt_type_en[$numAriaAppts] = "Consultation - Nursing" 	if($ApptDescription[$numAriaAppts]=~ m/Nursing Consult/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Consultation - Soins infirmiers" if($ApptDescription[$numAriaAppts]=~ m/Nursing Consult/i);
	  
	    # Nursing consult- if contains Nursing Consult 
	    $Aria_Appt_type_en[$numAriaAppts] = "Hydration - Nursing" 	if($ApptDescription[$numAriaAppts]=~ m/Hydration/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Hydratation - Soins infirmiers" if($ApptDescription[$numAriaAppts]=~ m/Hydration/i);
	  
	    # Intra Treatment Visit - if contains INTRA TREAT
	    $Aria_Appt_type_en[$numAriaAppts] = "Intra Treatment Visit" 	if($ApptDescription[$numAriaAppts]=~ m/INTRA TREAT/i);
	    $Aria_Appt_type_fr[$numAriaAppts] = "Intra Treatment Visit" 	if($ApptDescription[$numAriaAppts]=~ m/INTRA TREAT/i);
	  
	    # Find out the machine where the patient's appointment is at
	    #### Note: this could probably just check if ResourceType is machine...
	    print "Aria_Appt_type_en[$numAriaAppts]: $Aria_Appt_type_en[$numAriaAppts]<br>" if $verbose;
	    if($Aria_Appt_type_en[$numAriaAppts] eq "Treatment" || $Aria_Appt_type_en[$numAriaAppts] eq "CT Simulation" || $Aria_Appt_type_en[$numAriaAppts] eq "Brachytherapy")
	    {
	      my $machineSQL = "
		  SELECT DISTINCT 
			Machine.MachineId
		    FROM  
			variansystem.dbo.Machine Machine
		    WHERE 
			 Machine.ResourceSer = $ResourceSer[$numAriaAppts]
		";
	  
	      # print the SQL query for debugging
	      print "machineSQL: $machineSQL<br><p>\n" if $verbose;
	  
	      # prepare and check the query for the database
	      my $queryMachine= $dbh->prepare($machineSQL)
		#or die "Couldn't prepare machineSQL statement: " . $dbh->errstr;
		or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare machineSQL statement: " . $dbh->errstr . "\n\n";
	  
	      # submit queryMachine to server
	      $queryMachine->execute()
		#or die "Couldn't execute queryMachine statement: " . $queryMachine->errstr;
		or print CHECKINLOG "###$now, $location ERROR - Couldn't execute queryMachine statement: " . $queryMachine->errstr . "\n\n";
	  
	      my @data = $queryMachine->fetchrow_array();
	      $Aria_MachineId[$numAriaAppts] = $data[0];
	    }
	  
	    # Find out which doctor the patient's appointment is with
	    my $DoctorLastName;
	    if($ResourceType[$numAriaAppts] eq "Doctor")
	    {
	      my $DoctorSQL = "
		  SELECT DISTINCT 
			Doctor.LastName
		    FROM  
			variansystem.dbo.Doctor
		    WHERE 
			 Doctor.ResourceSer = $ResourceSer[$numAriaAppts]
		";
	  
	      # print the SQL query for debugging
	      print "DoctorSQL: $DoctorSQL<br><p>\n" if $verbose;
	  
	      # prepare and check the query for the database
	      my $queryDoctor= $dbh->prepare($DoctorSQL)
		#or die "Couldn't prepare DoctorSQL statement: " . $dbh->errstr;
		or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare DoctorSQL statement: " . $dbh->errstr . "\n\n";
	  
	      # submit queryMachine to server
	      $queryDoctor->execute()
		#or die "Couldn't execute DoctorSQL statement: " . $queryDoctor->errstr;
		or print CHECKINLOG "###$now, $location ERROR - Couldn't execute DoctorSQL statement: " . $queryDoctor->errstr . "\n\n";
	  
	      my @data = $queryDoctor->fetchrow_array();
	      $DoctorLastName = $data[0];
	      print "Doctor is $DoctorLastName<br>" if $verbose;
	    }

	    $numAriaAppts++;
	  } # End of Aria appointment search
  }

  ##############################################################################################
  #				Medivisit appointment search			               #
  ##############################################################################################
  my $sqlApptMedivisit = "
	  SELECT DISTINCT 
		Patient.PatientId$hospitalMV,
		Patient.FirstName,
		Patient.LastName,
		MediVisitAppointmentList.ScheduledDateTime, 
		MediVisitAppointmentList.AppointmentCode,
		MediVisitAppointmentList.ResourceDescription,
		(UNIX_TIMESTAMP(MediVisitAppointmentList.ScheduledDateTime)-UNIX_TIMESTAMP('$startOfToday_mysql'))/60 AS AptTimeSinceMidnight, 
		MediVisitAppointmentList.AppointmentSerNum,
		HOUR(MediVisitAppointmentList.ScheduledDateTime)
          FROM
		Patient,
		MediVisitAppointmentList
	 WHERE
		MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
		AND MediVisitAppointmentList.PatientSerNum = $PatientSerNum 
            	AND ( MediVisitAppointmentList.ScheduledDateTime >= \'$startOfToday_mysql\' )    
                AND ( MediVisitAppointmentList.ScheduledDateTime < \'$endOfToday_mysql\' )  
		AND ( MediVisitAppointmentList.Status = 'Open' OR MediVisitAppointmentList.Status = 'In Progress')
		AND MediVisitAppointmentList.ResourceDescription NOT LIKE \"%Dietetics%\"
		AND MediVisitAppointmentList.ResourceDescription NOT LIKE \"%prise%\"

		ORDER BY MediVisitAppointmentList.ScheduledDateTime
  "; 
#		AND ( 
#		      MediVisitAppointmentList.ResourceDescription = \'Chemotherapy Treatment - Glen\' 
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription = \'Treatment - Glen\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription = \'Bladder Installation\'
#		      OR 
#		      MediVisitAppointmentList.Resource		   = \'NSBLD\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Legler%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Tanguay%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Rajan%\'
#		      OR
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Sebag%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%David%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Reindl%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Berry%\'
#		      OR 
#		      MediVisitAppointmentList.ResourceDescription LIKE \'%Santaguida%\'
#		    )


		#LIMIT 1

  print "sqlApptMedivisit: $sqlApptMedivisit<br>" if $verbose;

  my $query= $dbh_mysql->prepare($sqlApptMedivisit) 
    #or die "Couldn't prepare sqlApptMedivisit statement: " . $dbh_mysql->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlApptMedivisit statement: " . $dbh_mysql->errstr . "\n\n";

  $query->execute() 
    #or die "Couldn't execute sqlApptMedivisit statement: " . $query->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlApptMedivisit statement: " . $query->errstr . "\n\n";

  # set up arrays to hold the Medivisit data pertaining to multiple appointments
  my @MV_PatientId;
  my @MV_PatientFirstName;
  my @MV_PatientLastName;
  my @MV_ScheduledStartTime;
  my @MV_ApptDescription;
  my @MV_Resource;
  my @MV_AptTimeSinceMidnight;
  my @MV_AppointmentSerNum;
  my @MV_Appt_type_en;
  my @MV_Appt_type_fr;
  my @MV_MachineId;
  my @MV_AptTimeHour;
  my @MV_AMorPM;

  print "<br>-------------Medivisit Appointment Search---------------------------<br>" if $verbose;
  my $numMedivisitAppts = 0;
  $MV_AptTimeSinceMidnight[0] = 999999999999999999999; # fail safe
  # lopp over all the Medivisit appointments for this patient today
  # The NEXT appointment will be the first (ie the zeroth) in the list
  while(my @data = $query->fetchrow_array())
  {
    print "<br>-------------Medivisit Appointment---------------------------<br>" if $verbose;
    # grab data from MySQL
    $MV_PatientId[$numMedivisitAppts]		= $data[0];
    $MV_PatientFirstName[$numMedivisitAppts]	= $data[1];
    $MV_PatientLastName[$numMedivisitAppts]	= $data[2];
    $MV_ScheduledStartTime[$numMedivisitAppts]	= $data[3];
    $MV_ApptDescription[$numMedivisitAppts]	= $data[4]; ###
    $MV_Resource[$numMedivisitAppts]		= $data[5];
    $MV_AptTimeSinceMidnight[$numMedivisitAppts]= $data[6];
    $MV_AppointmentSerNum[$numMedivisitAppts]	= $data[7];
    $MV_AptTimeHour[$numMedivisitAppts]		= $data[8];

    # appointment AM or PM
    if($MV_AptTimeHour[$numMedivisitAppts] >= 13)
    {
      $MV_AMorPM[$numMedivisitAppts] = "PM";
    }
    else
    {
      $MV_AMorPM[$numMedivisitAppts] = "AM";
    }

    my $MV_ResourceType 	= "Medivisit";
    my $MV_MachineId 		= "Medivisit";

    print "MV_Patient LastName: $MV_PatientLastName[$numMedivisitAppts] <br>" if $verbose;
    print "MV_ScheduledStartTime: $MV_ScheduledStartTime[$numMedivisitAppts] <br>" if $verbose;
    print "MV_ApptDescription: $MV_ApptDescription[$numMedivisitAppts]<br>" if $verbose;
    print "MV_Resource: $MV_Resource[$numMedivisitAppts]<br>" if $verbose;
    print "MV_ResourceType: $MV_ResourceType<br>" if $verbose;
    print "MV_AptTimeSinceMidnight: $MV_AptTimeSinceMidnight[$numMedivisitAppts]<br>" if $verbose;
    print "MV_AppointmentSerNum: $MV_AppointmentSerNum[$numMedivisitAppts]<br>" if $verbose;
    print "MV_AptTimeHour: $MV_AptTimeHour[$numMedivisitAppts]<br>" if $verbose;

    #------------------------------------------------------------------------
    # Is the medivisit appointment in the pilot? If yes, nothing to do. If no, then PilotOk = 0
    #------------------------------------------------------------------------
    my $Resource = $MV_Resource[$numMedivisitAppts];
    #Uncomment below when ready to initiate use of Resources.pm module
    my $checkPilot = 0;
    foreach my $r (@resource_list){
      if($Resource =~ m/$r/i)
      {
       print "Patient's appointment resource - $Resource - is in the pilot<br>" if $verbose;
       $checkPilot = 1;
      }
    }
    if($checkPilot eq 0) #Resource didnt match any resource in the list
    {
     $PilotOk = 0;
     print "<center><b>Patient's appointment resource - $Resource - is NOT in the pilot - patient will not be called by screens for this appointment</b></center><p>" if $verbose || $location =~ m/Reception/i;
    }



    # if(
    #     $Resource =~ m/Chemotherapy Treatment - Glen/i 
    #     || $Resource =~ m/Treatment - Glen/i 
    #     || $Resource =~ m/Oncologie Traitement - Glen/i 
    #     || $Resource =~ m/Bladder Installation/i 
    #     || $Resource =~ m/Aprikian/i 
    #     || $Resource =~ m/Legler/i 
    #     || $Resource =~ m/Rajan/i 
    #     || $Resource =~ m/Sebag/i 
    #     || $Resource =~ m/David/i 
    #     || $Resource =~ m/Reindl/i 
    #     || $Resource =~ m/Berry/i 
    #     || $Resource =~ m/Dr. M. Weber - Ortho/i 
    #     || $Resource =~ m/Santaguida/i 
    #     || $Resource =~ m/Jarzem/i 
    #     || $Resource =~ m/Fisher/i 
    #     || $Resource =~ m/Bernstein/i 
    #     || $Resource =~ m/Doulas/i 
    #     || $Resource =~ m/Baylis/i 
    #     || $Resource =~ m/Lacroix/i 
    #     || $Resource =~ m/Talbot/i 
    #     || $Resource =~ m/Tanzer/i 
    #     || $Resource =~ m/Asselah/i 
    #     || $Resource =~ m/Bouganim/i 
    #     || $Resource =~ m/Kassouf/i 
    #     || $Resource =~ m/Guo/i 
    #     || $Resource =~ m/France/i 
    #     || $Resource =~ m/Golan/i 
    #     || $Resource =~ m/K. Goulding - Ortho/i 
    #     || $Resource =~ m/Tanguay/i 
    #     || $Resource =~ m/Vanhuyse/i 
    #     || $Resource =~ m/Owen/i 
    #     || $Resource =~ m/Hirsh/i 
    #     || $Resource =~ m/Shieh/i 
    #     || $Resource =~ m/Ofiara/i 
    #     || $Resource =~ m/Chaudhury/i 
    #     || $Resource =~ m/buccale oncologie/i 
    #     || $Resource =~ m/Meguerditchian/i 
    #     || $Resource =~ m/Barkun/i 
    #     || $Resource =~ m/Metrakos/i 
    #     || $Resource =~ m/Meterissian/i 
    #     || $Resource =~ m/Dumitra/i 
    #     || $Resource =~ m/Palma/i 
    #     || $Resource =~ m/Foulkes/i 
    #     || $Resource =~ m/Weber/i 
    #     || $Resource =~ m/Whelton/i 
    #     || $Resource =~ m/Uro Onc Resident/i 
    #     || $Resource =~ m/Resident - MedGen/i 
    #     || $Resource =~ m/Cabrera/i 
    #     || $Resource =~ m/Zogopoulos/i 
    #     || $Resource =~ m/Arena/i 
    #     || $Resource =~ m/Melanoma/i 
    #     || $Resource =~ m/Tremblay/i 
    #     || $Resource =~ m/Wang/i 
    #     || $Resource =~ m/Loutfi/i 
    #     || $Resource =~ m/David/i 
    #     || $Resource =~ m/How/i 
    #     || $Resource =~ m/Hoffman/i 
    #     || $Resource =~ m/Wan Chow Wah/i 
    #     || $Resource =~ m/Desforges/i 
    #     || $Resource =~ m/Kaedbey/i 
    #     || $Resource =~ m/Salasidis/i 
    #     || $Resource =~ m/Shustik/i 
    #     || $Resource =~ m/Storring/i 
    #     || $Resource =~ m/Bazinet/i 
    #     || $Resource =~ m/Solymoss/i 
    #     || $Resource =~ m/Morris/i 
    #     || $Resource =~ m/Davison/i 
    #     || $Resource =~ m/Laneuville/i 
    #     || $Resource =~ m/Routy/i 
    #     || $Resource =~ m/Blake/i 
    #     || $Resource =~ m/Myeloma/i 
    #     || $Resource =~ m/Cournoyer/i 
    #     || $Resource =~ m/Tremblay/i 
    #     || $Resource =~ m/Popradi/i 
    #     || $Resource =~ m/Elkrief/i 
    #     || $Resource =~ m/Seguin/i 
    #     || $Resource =~ m/Fleiszer/i 
    #     || $Resource =~ m/How/i 
    #     || $Resource =~ m/Kavan/i 
    #     || $Resource =~ m/Transplant/i
    #     || $Resource =~ m/Zeng/i 
    #     || $Resource =~ m/Jardon/i 
    #     || $Resource =~ m/Gilbert/i 
    #     || $Resource =~ m/Gyne/i  
    #     || $Resource =~ m/Haem/i 
    #     || $Resource =~ m/Alcindor/i 
    #     #|| $Resource =~ m/NS - prise de sang/i 
    #  )
    #  {
    #    print "Patient's appointment resource - $Resource - is in the pilot<br>" if $verbose;
    #  }
    #  else
    #  {
    #    $PilotOk = 0;
    #    print "<center><b>Patient's appointment resource - $Resource - is NOT in the pilot - patient will not be called by screens for this appointment</b></center><p>" if $verbose || $location =~ m/Reception/i;
    #  }

    #------------------------------------------------------------------------
    # Set the Medivisit appointment type here - just appointment for now
    #------------------------------------------------------------------------
    $MV_Appt_type_en[$numMedivisitAppts] = "Appointment";
    $MV_Appt_type_fr[$numMedivisitAppts] = "rendez-vous";
 
    $numMedivisitAppts++;
  } # end of medivisit appointment search

  # If we have no appointment today in either Medivisit or Aria, return now with an error
  if( (!$MV_AppointmentSerNum[0] && $useAria == 0) || ($useAria == 1 && !$MV_AppointmentSerNum[0] && !$ScheduledActivitySer[0]) )
  {
    my $null = "";
    @returnValue = ("No Appointment",$null,$null,$null,$null,$null,$null);
    $log_message = "No appointment found for patient $MV_PatientLastName[0]";
    return @returnValue; 
  }
  print "<br>----------------------------------------<br>" if $verbose;

  ####################################################################################### 
  ################ Figure out Next Appointment and where it is ############# 
  ####################################################################################### 
  # If we have both an Aria and medivist appointment - figure out which is next 
  # (ie earliest =  less minutes since start of day). The patient should be told to wait
  # for their next appointment, even if checked in for all appointments

  # Take the first ([0]) Aria and the first medivisit appointment and compare them - the earliest
  # is the earliest
  ####################################################################################### 
  my $nextApptDescription;
  my $nextApptSystem;
  my $nextApptAMorPM;
  my $CheckInResource;

  print "MV_AptTimeSinceMidnight: $MV_AptTimeSinceMidnight[0], Aria AptTimeSinceMidnight: $AptTimeSinceMidnight[0]<br>" if $verbose && $useAria == 1;

  if( ($MV_AptTimeSinceMidnight[0] < $AptTimeSinceMidnight[0]) || !$useAria )
  {
    print "Next appointment is in Medivisit<br>" if $verbose;
    $nextApptSystem = "Medivisit"; 
    $nextApptAMorPM= $MV_AMorPM[0]; 
    $nextApptDescription = $MV_ApptDescription[0];
    $CheckInResource = $MV_Resource[0];
    print "MV CheckInResource: $CheckInResource<br>" if $verbose;
  }
  else
  {
    print "Next appointment is in Aria<br>" if $verbose;
    $nextApptSystem = "Aria"; 
    $nextApptAMorPM= $Aria_AMorPM[0]; 

    # Find the ResourceName for the given ResourceSer from Aria
    my $sqlResourceName = 
		"
  			SELECT DISTINCT
				vv_ResourceName.ResourceName
  			FROM
  				variansystem.dbo.vv_ResourceName vv_ResourceName
  			WHERE 
				vv_ResourceName.ResourceSer = $ResourceSer[0]
		";

    # print the SQL query for debugging
    print "SQL: $sqlResourceName<br><p>\n" if $verbose;

    # prepare and check the query for the database
    my $sqlResource_query = $dbh->prepare($sqlResourceName)
      #or die "Couldn't prepare sqlResourceName statement: " . $dbh->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlResourceName statement: " . $dbh->errstr . "\n\n";

    # submit queryAppt to server
    $sqlResource_query->execute()
      #or die "Couldn't execute sqlResourceName statement: " . $sqlResource_query->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlResourceName statement: " . $sqlResource_query->errstr . "\n\n";

    $CheckInResource = $sqlResource_query->fetchrow_array();
    print "Aria CheckInResource: $CheckInResource<br>" if $verbose;
  }
 
  print "<br>------------------------------ Next Appointment ---------------------------<br>" if $verbose;
  print "Next appointment is for $nextApptSystem (at resource: $CheckInResource) <br>" if $verbose;
  print "<br>---------------------------------------------------------------------------<br>" if $verbose;

  #------------------------------------------------------------------------
  # Figure out the appropriate waiting room for the NEXT appointment
  #------------------------------------------------------------------------
  my $CheckinVenue;
  my $CheckinVenueName;
  # ResourceSer     VenueName
  # 127     New Waiting Room
  # 128     Old Waiting Room
  # 176     Not Specified
  # 4921    Txt Waiting List
  # 5485    CHUM (CYBERKNIFE/LINAC)
  # 6275    EXAM ROOM 7
  # 6276    EXAM ROOM 2
  # 6277    EXAM ROOM 3
  # 6278    EXAM ROOM 4
  # 6279    EXAM ROOM 5
  # 6280    EXAM ROOM 5.5
  # 7599    EXAM ROOM 8
  # 7600    EXAM ROOM 1
  # 8224    S1 CLINIC Waiting Room S1 CLINIC 0 
  # 8225    RC WAITING ROOM Waiting Room RC WAITING ROOM 0 
  # 8226    S1 WAITING ROOM Waiting Room S1 WAITING ROOM 0 
  # 8227    TEST CENTRE WAIT Waiting Room TEST CENTRE WAITING ROOM 0 

  if($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3" || $location =~ m/ReceptionRC/i)
  {
    $CheckinVenue = 8225;
    $CheckinVenueName = "RC WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  elsif($location eq "DS1_1" || $location eq "DS1_2" || $location =~ m/ReceptionS1/i)
  {
    $CheckinVenue = 8226;
    $CheckinVenueName = "S1 WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  # Orthopedics
  elsif($location eq "Ortho_1" || $location eq "Ortho_2" || $location =~ m/ReceptionOrtho/i)
  {
    $CheckinVenue = 0;
    $CheckinVenueName = "ORTHO WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  else
  {
    $CheckinVenue = 0;
    $CheckinVenueName = "UNKNOWN";
  }

  # Most radiation oncology activities are at DS1 - use the earliest of the medivisit and Aria data - ie element [0]
  my $WaitingRoomWherePatientShouldWait;

  # Next appointment is in Aria
  if( $nextApptSystem eq "Aria" && ( ($Aria_MachineId[0] =~ m/TB_/i) || ($Aria_MachineId[0] =~ m/STX_/i) || ($Aria_Appt_type_en[0] eq "CT Simulation") || ($Aria_Appt_type_en[0] eq "Brachytherapy") 
   || ($Aria_Appt_type_en[0] eq "Injection - Nursing") || ($Aria_Appt_type_en[0] eq "Transfusion - Nursing")
   || ($Aria_Appt_type_en[0] eq "Consultation - Nursing") || ($Aria_Appt_type_en[0] eq "Hydration - Nursing") || ($Aria_Appt_type_en[0] eq "CT Simulation") 
   || ($Aria_Appt_type_en[0] eq "Treatment") ) ) ### Intratreats don't need to be here as they are checked in by the machines - consults and follow-ups depend on physician 
  {
    $WaitingRoomWherePatientShouldWait = "DS1"; 
    print "Checking in to Waiting Room: $WaitingRoomWherePatientShouldWait" if $verbose;
  }
  else # Next appointment is in Medivisit or not already determined for Aria
       # determine the waiting room (level DRC or DS1) of the next appointment from the scheduler
  { 
    # prepare the schedule query 
    #$AMorPM = "AM"; # set AM for testing
    #$DayOfWeek_Abbreviated = "Thu"; 
    
    # The CheckInResource variable contains the resource of the NEXT appointment, whether Aria or Medivisit
    my $level_sql = "
			SELECT ExamRoom.Level 
			FROM 
				ClinicResources,
				ClinicSchedule,
				ExamRoom
			WHERE 
				ClinicResources.ResourceName = \"$CheckInResource\"
				AND ClinicResources.ClinicScheduleSerNum =  ClinicSchedule.ClinicScheduleSerNum
				AND ClinicSchedule.DAY =  \"$DayOfWeek_Abbreviated\"
				AND ClinicSchedule.AMPM =  \"$nextApptAMorPM\"
				AND ClinicSchedule.ExamRoomSerNum = ExamRoom.ExamRoomSerNum
			LIMIT 1
		    ";

    print "level_sql: $level_sql<br>" if $verbose;

    $query= $dbh_mysql->prepare($level_sql) 
      #or die "Couldn't prepare level_sql statement: " . $dbh_mysql->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare level_sql statement: " . $dbh_mysql->errstr . "\n\n";

    $query->execute() 
      #or die "Couldn't execute level_sql statement: " . $query->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't execute level_sql statement: " . $query->errstr . "\n\n";

    my $level = $query->fetchrow_array();  
    #$level = "unknown" if !$level;

    # If no level was provided (ie the appointment resource is most likely a hanging clinic
    # then we should set the level to the level of the checkin kiosk
    if(!$level)
    { 
      if($location =~ m/RC_/i)
      {
        $level = "RC"; 
        $WaitingRoomWherePatientShouldWait = "DRC"; 
        print "Got DRC<br>" if $verbose;
      }
      if($location =~ m/S1_/i)
      {
        $level = "S1";
        $WaitingRoomWherePatientShouldWait = "DS1"; 
      }
      if($location =~ m/Ortho_/i)
      {
        $level = "Ortho";
        $WaitingRoomWherePatientShouldWait = "OrthoWaitRoom"; 
      }
    }
    elsif($level eq "RC")
    {
        $WaitingRoomWherePatientShouldWait = "DRC"; 
    }    
    elsif($level eq "S1")
    {
        $WaitingRoomWherePatientShouldWait = "DS1"; 
    }    


    print "level: $level<br>" if $verbose;
    print "Checking in to Waiting Room: $WaitingRoomWherePatientShouldWait<br>" if $verbose;
  }

  # Patients for whom the next appointment is a blood test, should be sent to the test centre
  # and also checked into the test centre
  if($CheckInResource eq "NS - prise de sang/blood tests pre/post tx")
  {
    print "******** This patient has a blood test first ****************<br>" if $verbose;

    # checking patient into test centre waiting room so that it is clear to all that they
    # are getting a blood test 
    $CheckinVenue = 8227;
    $CheckinVenueName = "TEST CENTRE WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
 
    $WaitingRoomWherePatientShouldWait = "TestCentre"; 
  }

  # Patients for whom the next appointment is an ortho appointment that needs an x-ray
  # should be sent to radiology to have the x-ray taken, if this is their first check
  # in for their ortho appointment. Thus, need to see if the patient has already checked
  # in for their ortho appointment and direct accordingly
  if($location =~ m/Ortho/i ) 
  {
    print "******** This patient is Ortho ****************<br>" if $verbose;
    print "******** checking if already checked in ****************<br>" if $verbose;
    # Simply check the PatientLocation table to see if they have already checked in
    
    my $sqlCheckIfCheckedIn = 
		"
			SELECT DISTINCT
				PatientLocationRevCount
			FROM
				PatientLocation
			WHERE
				PatientLocation.AppointmentSerNum = $MV_AppointmentSerNum[0]
		";

    print "<p>sqlCheckIfCheckedIn: $sqlCheckIfCheckedIn<br>" if $verbose;

    my $query= $dbh_mysql->prepare($sqlCheckIfCheckedIn) 
      #or die "Couldn't prepare sqlCheckIfCheckedIn statement: " . $dbh_mysql->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlCheckIfCheckedIn statement: " . $dbh_mysql->errstr . "\n\n";

    $query->execute() 
      #or die "Couldn't execute sqlCheckIfCheckedIn statement: " . $query->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlCheckIfCheckedIn statement: " . $query->errstr . "\n\n";

    my @data = $query->fetchrow_array();  

    # grab data from MySQL
    my $PatientLocationRevCount = $data[0];

    # No existing checkin - then send for X-ray
    #if($PatientLocationRevCount < 100)
    if( ($nextApptDescription =~ m/NP-XR/i || $nextApptDescription =~ m/RTFU-XR/i) && !$PatientLocationRevCount)
    {
      print "******** This patient should go for an x-ray ****************<br>" if $verbose;
      $CheckinVenue = 0;
      $CheckinVenueName = "SENT FOR X-RAY";
      print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
      $WaitingRoomWherePatientShouldWait = "RadiologyMGH"; 
    }
    elsif($PatientLocationRevCount) # patient already checked in so must be back from x-ray
    {
      print "******** This patient has already been to x-ray ****************<br>" if $verbose;
      $CheckinVenue = 0;
      $CheckinVenueName = "BACK FROM X-RAY/PHYSIO";
      print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
      $WaitingRoomWherePatientShouldWait = "OrthoWaitRoom"; 
    }
 
  }

  ##########################################################################################
  # Check the patient in for ALL Aria appointments
  ##########################################################################################
  my @Aria_CheckinStatus;
 
  if($useAria == 1)
  {
	  for(my $appointment = 0; $appointment < $numAriaAppts; $appointment++)
	  {
	    $Aria_CheckinStatus[$appointment] = "Aria not successful"; # fail safe
	    print "<br>------------------------------------------------------------<br>"if $verbose;
	    print "<br>Checking into Aria appointment #$appointment <br>" if $verbose;

	    my $checkinSQL= "
		exec variansystem.dbo.vp_CheckInPatient \@nVenueLocationSer = $CheckinVenue, \@nScheduledActivitySer = $ScheduledActivitySer[$appointment], \@strComment = null, \@strHstryUserName=N'$location'
		";

	    # print the SQL query for debugging
	    print "checkinSQL: $checkinSQL<br><p>\n" if $verbose;
	
	    # prepare and check the query for the database
	    my $queryCheckin= $dbh->prepare($checkinSQL)
	      #or die "Couldn't prepare vp_CheckInPatient statement: " . $dbh->errstr;
	      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare vp_CheckInPatient statement: " . $dbh->errstr . "\n\n";

	    # submit queryCheckin to server

	    if($queryCheckin->execute())
	    {
	      $Aria_CheckinStatus[$appointment] = "OK";
	      print CHECKINLOG "###$now, $location Appointment checked in Ok - $checkinSQL\n\n";
	    }
	    else
	    {
	      $Aria_CheckinStatus[$appointment] = $queryCheckin->errstr;
	      print CHECKINLOG "###$now, $location ERROR - Couldn't checkin patient: " . $queryCheckin->errstr . "\n\n";
	    }

	    #--------------------------------------------------------------------------------------------
	    # As  we have an Aria patient, we need to check if the patient has a photo
	    #--------------------------------------------------------------------------------------------
	    my $sqlPhoto = "SELECT DISTINCT 
				DATALENGTH(Photo.Picture) AS PicSize
			  FROM variansystem.dbo.Photo
			  WHERE Photo.PatientSer = $PatientSer";

	    # print the SQL query for debugging
	    print "SQL: $sqlPhoto<br><p>\n" if $verbose;

	    # prepare and check the query for the database
	    my $queryPhoto= $dbh->prepare($sqlPhoto)
	      #or die "Couldn't prepare sqlPhoto statement: " . $dbh->errstr;
	      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlPhoto statement: " . $dbh->errstr . "\n\n";

	    # submit queryPhoto to server
	    $queryPhoto->execute()
	      #or die "Couldn't execute sqlPhoto statement: " . $queryPhoto->errstr;
	      or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlPhoto statement: " . $queryPhoto->errstr . "\n\n";

	    my @data = $queryPhoto->fetchrow_array();
	    my $PicSize = $data[0];

	    $PhotoOk = 0 if(!$PicSize && $PatientSer ne "NULL" && $Aria_Appt_type_en[$appointment] ne "Follow-up" && $Aria_Appt_type_en[$appointment] ne "Consultation"); # we don't worry about consults and follow-ups not having photos. 

	    print "Patient has been checked into Aria for Aria appointment #$appointment... status: $Aria_CheckinStatus[$appointment]" if $verbose;
	    print "Patient has been checked into Aria for Aria appointment #$appointment<br>" if $verbose || $location =~ m/Reception/i;

	    print "<br>===================== Finished Aria checkin =====================<br>" if $verbose; 

	  } # end of Aria checkin

	  ####### Return Value for Aria ###########################
	  # Set the Aria return value for the first appointment only
	  print "Patient's photo status: $PhotoOk<br>" if $verbose;
	  @returnValueAria = ($Aria_CheckinStatus[0],$ScheduledStartTime_en[0],$ScheduledStartTime_fr[0],$Aria_Appt_type_en[0],$Aria_Appt_type_fr[0],$WaitingRoomWherePatientShouldWait,$Aria_MachineId[0],$PhotoOk,$PilotOk);
	  print "returnValueAria: @returnValueAria<br>" if $verbose;
  } # end of useAria

  ##########################################################################################
  # Check the patient in for all Medivisit appointments
  # - loop over all medivisit appointments and check the patient in for each...
  ##########################################################################################
  my @MV_CheckinStatus;
  for(my $appointment = 0; $appointment < $numMedivisitAppts; $appointment++)
  {
    print "<br>------------------------------------------------------------<br>"if $verbose;
    print "<br>Checking into Medivisit appointment #$appointment <br>" if $verbose;
    #---------------------------------------------------------------------------------------------
    # First, check for an existing entry in the patient location table for this appointment
    #---------------------------------------------------------------------------------------------
    my $sqlMV_checkCheckin = "
	  SELECT DISTINCT 
                PatientLocation.PatientLocationSerNum,
                PatientLocation.PatientLocationRevCount,
                PatientLocation.CheckinVenueName,
                PatientLocation.ArrivalDateTime
          FROM
		PatientLocation
	 WHERE
                PatientLocation.AppointmentSerNum = $MV_AppointmentSerNum[$appointment]
    "; 

    print "<p>sqlMV_checkCheckin: $sqlMV_checkCheckin<br>" if $verbose;

    my $query= $dbh_mysql->prepare($sqlMV_checkCheckin) 
      #or die "Couldn't prepare sqlMV_checkCheckin statement: " . $dbh_mysql->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlMV_checkCheckin statement: " . $dbh_mysql->errstr . "\n\n";

    $query->execute() 
      #or die "Couldn't execute sqlMV_checkCheckin statement: " . $query->errstr;
      or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlMV_checkCheckin statement: " . $query->errstr . "\n\n";

    my @data = $query->fetchrow_array();  

    # grab data from MySQL
    my $PatientLocationSerNum			= $data[0];
    my $PatientLocationRevCount			= $data[1];
    my $CheckinVenueNamePrevious			= $data[2]; # use the new venue, not the old
    my $ArrivalDateTime				= $data[3];

    #---------------------------------------------------------------------------------------------
    # If there is an existing entry in the patient location table, take the values and 
    # insert them into the PatientLocationMH table
    #---------------------------------------------------------------------------------------------
    if($PatientLocationSerNum)
    {
      print "inserting into MH table<br>" if $verbose;
      my $sql_insert_previousCheckin= "INSERT INTO 
		PatientLocationMH(PatientLocationSerNum,PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime) 
		VALUES ('$PatientLocationSerNum','$PatientLocationRevCount','$MV_AppointmentSerNum[$appointment]','$CheckinVenueNamePrevious','$ArrivalDateTime')";
      $query= $dbh_mysql->prepare($sql_insert_previousCheckin);
      $query->execute(); 
    }

    #---------------------------------------------------------------------------------------------
    # Put an entry into the PatientLocation table
    # - first time entry the RevCount = 0, increment it to 1
    # - not first time entry, increment by one the RevCount of the previous entry
    #---------------------------------------------------------------------------------------------
    $PatientLocationRevCount++;
    my $sql_insert_newCheckin= "INSERT INTO 
			PatientLocation(PatientLocationRevCount,AppointmentSerNum,CheckinVenueName,ArrivalDateTime) 
			VALUES ('$PatientLocationRevCount','$MV_AppointmentSerNum[$appointment]','$CheckinVenueName',NOW())";

    print "sql_insert_newCheckin: $sql_insert_newCheckin<br>" if $verbose;
    $query= $dbh_mysql->prepare($sql_insert_newCheckin);

    if($query->execute())
    {
      $MV_CheckinStatus[$appointment] = "OK";
    }
    else
    {
      $MV_CheckinStatus[$appointment] = $query->errstr;
    }

    # if there was an existing entry in the patient location table, delete it now 
    if($PatientLocationSerNum)
    {
      print "deleting existing entry in PatientLocation table<br>" if $verbose;
      my $sql_delete_previousCheckin= "DELETE FROM PatientLocation WHERE PatientLocationSerNum=$PatientLocationSerNum";

      $query= $dbh_mysql->prepare($sql_delete_previousCheckin);
      $query->execute(); 

      print "deleted...<br>" if $verbose;
    }

    print "Patient has been checked into Medivist for Medivisit appointment #$appointment... status: $MV_CheckinStatus[$appointment]" if $verbose;
    print "<center>Patient has been checked into appointment <i>$MV_Resource[$appointment]</i> at <b>$MV_ScheduledStartTime[$appointment]</b>... status: $MV_CheckinStatus[$appointment]<br></center>" if $verbose || $location =~ m/Reception/i;
    print "<br>===================== Finished Medivisit checkin =====================<br>" if $verbose; 
   
  } # end of Medivisit checkin

  ####### Return Value for Medivisit ###########################
  # Set the return value
  @returnValueMedivisit = ($MV_CheckinStatus[0],$MV_ScheduledStartTime[0],$MV_ScheduledStartTime[0],$MV_Appt_type_en[0],$MV_Appt_type_fr[0],$WaitingRoomWherePatientShouldWait,$MV_MachineId[0],$PhotoOk,$PilotOk,$nextApptSystem);
  print "returnValueMedivisit: @returnValueMedivisit<br>" if $verbose;


  ##################################
  # The return value should just be for the NEXT appointment, whether it is Aria or Meidivist
 
  print "Returning for $nextApptSystem<br>" if $verbose;
  return @returnValueAria 	if $nextApptSystem eq "Aria";
  return @returnValueMedivisit 	if $nextApptSystem eq "Medivisit";

  ##################################

}

sub getResourceList 
{
	my @resourceList = (
	"Chemotherapy Treatment - Glen",
	"Treatment - Glen",
	"Oncologie Traitment - Glen",
	"Oncologie Traitement - Glen",
	"Bladder Installation",
	"Aprikian",
	"Legler",
	"Rajan",
	"Sebag",
	"David",
	"Reindl",
	"Berry",
	"Dr. M. Weber - Ortho",
	"Santaguida",
	"Jarzem",
	"Fisher",
	"Bernstein",
	"Doulas",
	"Baylis",
	"Lacroix",
	"Talbot",
	"Tanzer",
	"Asselah",
	"Bouganim",
	"Kassouf",
	"Guo",
	"France",
	"Golan",
	"K. Goulding - Ortho",
	"Tanguay",
	"Vanhuyse",
	"Owen",
	"Hirsh",
	"Shieh",
	"Ofiara",
	"Chaudhury",
	"buccale oncolgie",
	"buccale oncologie",
	"Meguerditchian",
	"Barkun",
	"Metrakos",
	"Meterissian",
	"Dumitra",
	"Palma",
	"Foulkes",
	"Weber",
	"Whelton",
	"Uro Onc Resident",
	"Resident - MedGen",
	"Cabrera",
	"Zogopoulos",
	"Arena",
	"Melanoma",
	"Tremblay",
	"Wang",
	"Loutfi",
	"David",
	"How",
	"Hoffman",
	"Wan Chow Wah",
	"Desforges",
	"Kaedbey",
	"Salasidis",
	"Shustik",
	"Storring",
	"Bazinet",
	"Solymoss",
	"Morris",
	"Davison",
	"Laneuville",
	"Routy",
	"Blake",
	"Myeloma",
	"Cournoyer",
	"Tremblay",
	"Popradi",
	"Elkrief",
	"Seguin",
	"Fleiszer",
	"How",
	"Kavan",
	"Transplant",
	"Zeng",
	"Jardon",
	"Gilbert",
	"Gyne",
	"Haem",
	"Turcotte",
	"Hings",
	"Tchervenkov",
	"Alcindor",
	"Thirlwell",
	"Groleau",
	"Mihalcioiu",
	"Molly",
	"Ostomy",
	"Moniteur clinique",
	"Urology - RN",
	"Onco HPB Surgery"
	);

	return @resourceList;
}


exit;
