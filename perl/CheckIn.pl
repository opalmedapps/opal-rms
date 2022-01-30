#!/usr/bin/perl

#------------------------------------------------------------------------
# PERL-CGI- script to check a patient in
#
# Input parameters: various
#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
$SIG{__DIE__} = sendToReception;
use strict;
use v5.26;
use lib "./system/modules";
#use warnings;
#use diagnostics;
use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Date::Calc;
#use Date::Calc qw( Standard_to_Business Today Business_to_Standard );
use Date::Calc qw(Day_of_Week_to_Text Day_of_Week Decode_Month Today Now Decode_Date_US Today_and_Now Delta_DHMS Add_Delta_Days Delta_Days Add_Delta_DHMS Day_of_Week_Abbreviation);
use Time::Piece;
use HTTP::Request;
use LWP::UserAgent;
use JSON;
use File::Basename;
use Parse::CSV;

use LoadConfigs;

#------------------------------------------------------------------------
# Modules needed for SOAP webservices
#------------------------------------------------------------------------
use Data::Dumper;

#------------------------------------------------------------------------
# Important variables
#------------------------------------------------------------------------
my $systemPaths = LoadConfigs::GetConfigs("path");

my $checkin_script = "$systemPaths->{'BASE_URL'}/perl/CheckIn.pl";
my $images = $systemPaths->{'IMAGE_URL'};
my $SMS_url = "$systemPaths->{'BASE_PATH'}/php/api/private/v1/patient/sms/sendSmsOldKiosk";
my $logfile_location = "$systemPaths->{'LOG_PATH'}/kiosk";
my $logging = 1; # Log data to a text file
my $adtConnected = 0;
my $PhotoStatus;
my $PilotStatus;
my $ReloadFinal = 11; # 10 second to reload the final screen
#my $ReloadMid = 3; # 3 seconds to display the patient name by default
my $ReloadMid = 4; # 8 seconds to display the patient name by default - Measles

my $exportSettings = LoadConfigs::GetConfigs("oie");
my $ariaCheckinUrl = $exportSettings->{"OIE_URL"} ."/Patient/Location";
my $ariaPhotoUrl = $exportSettings->{"OIE_URL"} ."/Patient/Photo";

my $site = "RVH";

#user agent for http calls
my $ua = LWP::UserAgent->new( ssl_opts => { verify_hostname => 0 } );
$ua->timeout(10);

#------------------------------------------------------------------------
# Start the webpage here so it can be used in verbose mode
#------------------------------------------------------------------------
print "Content-type: text/html\n\n";

#------------------------------------------------------------------------
# Parse the input parameters
# verbose shows debugging information
# location is the location of the kiosk - directions are relative to this location
# PatientId is the ID of the patient that is being checked in
# PatientSer is the PatientSer of the patient that is being checked in
# PatientSerNum is the db PatientSerNum of the patient that is being checked in
#------------------------------------------------------------------------
my $verbose = 0;
my $verbose         = param("verbose");
my $location        = param("location");
my $PatientId       = param("PatientId");
my $PatientSer      = param("PatientSer");
my $PatientSerNum   = param("PatientSerNum");
my $PhotoStatus     = param("PhotoStatus");
my $PilotStatus     = param("PilotStatus");

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
#------------------------------------------------------------------------
my $mrnType;#Used in check on Patient MRN type in isramqExpired function
if($location eq "Ortho_1" || $location eq "Ortho_2")
{
  # Uncomment when ready to implement
  print "mrnType is MG\n" if $verbose;
  $mrnType = "MG";
}
else
{
  print "mrnType is MR\n" if $verbose;
  $mrnType = "MR";
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
my $red         = "rgb(255,0,0)";
my $green       = "rgb(34,139,34)";
my $blue        = "rgb(0,0,255)";
my $black       = "rgb(0,0,0)";
my $white       = "rgb(255,255,255)";
my $gray        = "rgb(128,128, 128)";
my $lightgray   = "rgb(224,224,224)";
my $paleblue    = "rgb(173,216,230)";
my $palered     = "rgb(240,128,128)";
my $palegreen   = "rgb(152,251,152)";
my $darkgreen   = "rgb(51,153,51)";
my $mghred      = "rgb(162,53,96)";
#my $darkgreen   = "rgb(0,102, 0)";


# Variable for arrows
my $arrows = 1;

# Message goes at the top
my $message_txtcolor    = $black;
my $message_bgcolor     = $darkgreen;


# Ortho kiosks: The kiosk outside is Ortho_1 and inside is Ortho_2
if($location eq "Ortho_1" || $location eq "Ortho_2")
{
  $message_bgcolor = $mghred;
}

if($location =~ m/Reception/i)
{
  $message_bgcolor = $blue;
}


my $message_txtcolor = $white;

my $MainMessage_fr = "Enregistrement";
my $MainMessage_en = "Check in";

# Appointment details go below the message
my $subMessage_bgcolor = $white;
my $subMessage_txtcolor = $black;
#my $subMessage_fr = "<center>Veuillez scanner votre carte soleil pour vous enregistrer.<br> <span style=\"background-color: #FFFFE0\"><b><font color='red'>Veuillez tenir votre carte &agrave environ 10 cm du lecteur.</font></b></span></center>";
# my $subMessage_fr = "<center>Veuillez scanner votre carte soleil pour vous enregistrer.<br> </center>";
my $subMessage_fr           = "<center>Veuillez entrer le numero de dossier medical du patient pour l'enregistrer <br></center>";
#my $subMessage_en = "<center>Please scan your medicare card to check in.<br> <span style=\"background-color: #FFFFE0\"><b><font color='red'>Please hold card about 10 cm from scanner.</font></b></span></center>";
# my $subMessage_en = "<center>Please scan your medicare card to check in.<br> </center>";
my $subMessage_en       = "<center>Please enter the patient MRN to check in <br></center>";
my $log_message;

# Appointment details go below the message
my $middleMessage_bgcolor   = $white;
my $middleMessage_txtcolor  = $black;

# Instructions are found to the left of the photo
my $middleMessage_bgcolor   = $white;
my $middleMessage_txtcolor  = $black;
my $middleMessage_fr        = "";
my $middleMessage_en        = "";


# Use a refresh to continuously refresh the page - this ensures that Google Chrome does not crash the page thinking it
# is inactive. It also allows for automatic updates and provides continuous logging to the log file to remotely check
# that all the kiosks are still alive
my $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"200\; URL=$checkin_script?location=$location&verbose=$verbose\">";

my $shortline = "<img src=\"$images/line_short.png\">";
$shortline = "" if $location =~ m/Reception/i;

my $middleMessage_image = "<img border=\"$flashBorder\" width=\"614\" src=\"$images/animation.gif\">";
$middleMessage_image = "<img border=\"$flashBorder\" width=\"214\" src=\"$images/animation.gif\">" if $location =~ m/Reception/i;
$log_message = "default message, $location, $subMessage_en";

# Waiting Room details
my $DestinationWaitingRoom;

#------------------------------------------------------------------------
# Set the location - can be done using location barcodes (DRC_1, DS1_1, DRC_2)
#------------------------------------------------------------------------
my $DRC_1 = "<img src=\"$images/DRC_1_Alone.gif\">";
my $DRC_2 = "<img src=\"$images/DRC_2_Alone.gif\">";
my $DRC_3 = "<img src=\"$images/DRC_3_Alone.gif\">";

my $DS1_1 = "<img src=\"$images/DS1_1_Alone.gif\">";
my $DS1_2 = "<img src=\"$images/DS1_2_Alone.gif\">";

my $Ortho_1 = "<img src=\"$images/Ortho_1_Alone.gif\">";
my $Ortho_2 = "<img src=\"$images/Ortho_2_Alone.gif\">";

my $location_image;
$location_image = $DRC_1 if $location eq "DRC_1";
$location_image = $DRC_2 if $location eq "DRC_2";
$location_image = $DRC_3 if $location eq "DRC_3";

$location_image = $DS1_1 if $location eq "DS1_1";
$location_image = $DS1_2 if $location eq "DS1_2";
$location_image = $DS1_1 if $location eq "DS1_3";


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
if($PatientId eq "DS1_3")
{
  $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0\; URL=$checkin_script?location=DS1_3\">" ;
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
    #$message_bgcolor   = $darkgreen;
    $message_txtcolor   = $white;
    $MainMessage_fr     = "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en     = "Please go to the reception";
    $subMessage_fr      = "Impossible de vous enregistrer en ce moment";
    $subMessage_en      = "Unable to check you in at this time";
    $middleMessage_fr   = "<b></b>";
    $middleMessage_en   = "<b></b>";
    $arrows             = 1;
    $DestinationWaitingRoom = "reception";
    #$middleMessage_image   = "<img src=\"$images/reception_alone.png\">";
    $middleMessage_image    = "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image    = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message = "$PatientId, $location, $subMessage_en";
  }
  # Expired RAMQ - send to admitting
  elsif($RAMQExpired eq 1)
  {
     # Tell the patient to go to the reception since he/she has not been located
     #$message_bgcolor  = $darkgreen;
     $message_txtcolor  = $white;
     $MainMessage_fr    = "Carte d'h&ocirc;pital expir&eacute;e";
     $MainMessage_en    = "Hospital Card Expired";

     $subMessage_fr = "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">Veuillez vous rendre au bureau des admissions &agrave; <b>C RC.0046</b> pour renouveler votre carte d'h&ocirc;pital.</span><br>";
     $subMessage_fr = "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">Veuillez vous rendre au bureau des admissions &agrave; <b>L6-130</b> pour renouveler votre carte d'h&ocirc;pital.</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");
     #$subMessage_fr = "<span style=\"background-color: #ff0000\">Impossible de vous enregistrer en ce moment.</span> <span style=\"background-color: #ffff00\">V&eacute;rifier &agrave la r&eacute;ception</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");

     $subMessage_en = "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to Admitting at <b>C RC.0046</b> to renew your hospital card.</span><br>";
     $subMessage_en = "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to Admitting at <b>L6-130</b> to renew your hospital card.</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");
     #$subMessage_en = "<span style=\"background-color: #ff0000\">Unable to check you in at this time.</span> <span style=\"background-color: #ffff00\">Please go to the reception</span><br>" if ($location eq "Ortho_1" || $location eq "Ortho_2");

     $middleMessage_fr  = "<b></b>";
     $middleMessage_en  = "<b></b>";
     $arrows            = 1;
     $DestinationWaitingRoom = "MGH_Admissions";
     #$middleMessage_image  = "<img src=\"$images/reception_alone.png\">";
     $middleMessage_image    = "<img src=\"$images/Reception_generic.png\">";
     $middleMessage_image    = "<img width=\"614\" src=\"$images/RV_Admissions.png\">";
     $middleMessage_image    = "<img width=\"614\" src=\"$images/MGH_Admissions.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");
     #$middleMessage_image = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

     # reload to the default page after 20 seconds
     $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

     $log_message = "$PatientId, $location, $subMessage_en";
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
    $arrows = 0;
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$ReloadMid\; URL=$checkin_script?PatientId=$PatientId&PatientSer=$PatientSer&PatientSerNum=$PatientSerNum&location=$location&verbose=$verbose\">";

    $log_message = "$PatientId, $location, $subMessage_en";
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
  my ($CheckinStatus,$ScheduledStartTime_en,$ScheduledStartTime_fr,$Appt_type_en,$Appt_type_fr,$WaitingRoom,$TreatmentRoom,$PhotoStatus,$PilotStatus) = CheckinPatient($PatientSer,$PatientSerNum,$begin,$begin_mysql,$end,$end_mysql);

  print "checkin code: ($CheckinStatus,$ScheduledStartTime_en,$ScheduledStartTime_fr,$Appt_type_en,$Appt_type_fr,$WaitingRoom,$TreatmentRoom,$PhotoStatus,$PilotStatus) = CheckinPatient($PatientSer,$PatientSerNum,$begin,$begin_mysql,$end,$end_mysql)<br>" if $verbose;
  #****************************************************************************
  #****************************************************************************

  #============================================================================
  # Send SMS Message, will go to patient if he/she is registered in ORMS
  #============================================================================
  if($CheckinStatus eq "OK")
  {
    my $message_EN = "MUHC - Cedars Cancer Centre: You are checked in for your appointment(s).";
    my $message_FR = "CUSM - Centre du cancer des Cèdres: Votre(vos) rendez-vous est(sont) enregistré(s)";

    $middleMessage_image = "";

    # if($location eq "Ortho_1" || $location eq "Ortho_2"|| $location eq "ReceptionOrtho")
    # {
    # $message_EN = "MGH - Orthopedics: You are checked in for your appointment(s).";
    # $message_FR = "HGM - Orthopédie: Votre(vos) rendez-vous est(sont) enregistré(s)";
    # }

    my $SMS_message = "php $SMS_url --patientId=\"$PatientSerNum\" --messageEN=\"$message_EN\" --messageFR=\"$message_FR\"";
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

    # if($location eq "Ortho_1" || $location eq "Ortho_2"|| $location eq "ReceptionOrtho")
    # {
    # $message_EN = "MGH - Orthopedics: Unable to check-in for one or more of your appointment(s). Please go to a reception.";
    # $message_FR = "HGM - Orthopédie: Impossible d'enregistrer un ou plusieurs de vos rendez-vous. SVP vérifier à la réception";
    # }

    my $SMS_message = "php $SMS_url --patientId=\"$PatientSerNum\" --messageEN=\"$message_EN\" --messageFR=\"$message_FR\"";
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
    #$message_bgcolor         = $green;
    $message_txtcolor         = $white;

    # Check the patient destination and give appropriate directions - generally, tell the patient to go to a waiting room
    print "TreatmentRoom: $TreatmentRoom" if $verbose;
#    if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i)
#    {
#      $subMessage_fr         = "Veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur l'&eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.<br/><b>Rendez-vous :</b> $Appt_type_fr ($ScheduledStartTime_fr)";
#      $subMessage_en         = "Please have a seat in the waiting room. Your name will appear on the screen when you are called.<br/><b>Appointment:</b> $Appt_type_en ($ScheduledStartTime_en)";
#      $log_message         = "$PatientId, $location, $subMessage_en";
#    }
    my $Aptinfo_en;
    my $Aptinfo_fr;
    #$Aptinfo_fr = "<b> Rendez-vous :</b> <span style=\"background-color: #ffff00\">$Appt_type_fr ($ScheduledStartTime_fr)</span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    #$Aptinfo_en = "<b> Appointment:</b> <span style=\"background-color: #ffff00\">$Appt_type_en ($ScheduledStartTime_en)</span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    $Aptinfo_fr = "<b> Rendez-vous :</b> <span style=\"background-color: #ffff00\">$Appt_type_fr </span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);
    $Aptinfo_en = "<b> Appointment:</b> <span style=\"background-color: #ffff00\">$Appt_type_en </span>" if ($TreatmentRoom =~ m/TB_/i || $TreatmentRoom =~ m/STX_/i);

    # destination is upstairs but checkin location is downstairs
    if($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "DRC" && ($location eq "DS1_1" || $location eq "DS1_2" || $location eq "DS1_3"))
    {
        $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
        $MainMessage_en         = "You are Checked In";
    #   $subMessage_fr         = "<span style=\"background-color: #ff0000\">Veuillez enregistrer &agrave la r&eacute;ception <b>en haut au rez de chauss&eacute;e</b>.</span> ";
        $subMessage_fr         = "<span style=\"background-color: yellow\">Vous &ecirc;tes enregistr&eacute;. Veuillez sortir du Centre de cancer et attendre d'être appelé par SMS ou retourner 5 minutes avant votre rendez-vous.</span> $Aptinfo_fr";
    #   $subMessage_en         = "<span style=\"background-color: #ff0000\">Please check in at the reception <b>upstairs on the ground floor</b>.</span>";
        $subMessage_en      = "<span style=\"background-color: yellow\">Please leave the Cancer Centre and wait to be called by SMS or come back only 5 minutes before your appointment time.</span> $Aptinfo_en";
      #$subMessage_fr         = "<span style=\"background-color: #ff0000\">Veuillez prendre place dans la salle d'attente <b>en haut au rez de chauss&eacute;e</b>.</span> $Aptinfo_fr";
      #$subMessage_en         = "<span style=\"background-color: #ff0000\">Please have a seat in the waiting room <b>upstairs on the ground floor</b>.</span> $Aptinfo_en";
      $log_message         = "$PatientId, $location, $subMessage_en";

    }
    # destination is TestCentre and checkin location is upstairs
    # elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "TestCentre" && ($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3"))
    # {
    #   $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
    #   $MainMessage_en         = "You are Checked In";
    #   $subMessage_fr         = "<span style=\"background-color: #ff0000\">Si vous n'avez pas encore fait votre pr&eacute;l&egrave;vement sanguin, veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement.</span> $Aptinfo_fr Autrement, veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.";
    #   $subMessage_en         = "<span style=\"background-color: #ff0000\">If you did not already have your blood test, please check in at the Test Centre Reception</b>.</span> $Aptinfo_en Otherwise, please have a seat in the waiting room. Your name will appear on the TV screens when you are called.";
    #   #$subMessage_fr         = "<span style=\"background-color: #ff0000\">Veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement.</span> $Aptinfo_fr";
    #   #$subMessage_en         = "<span style=\"background-color: #ff0000\">Please checkin at the Test Centre Reception</b>.</span> $Aptinfo_en";
    #   $log_message         = "$PatientId, $location, $subMessage_en";

    # }
    # destination is TestCentre and checkin location is downstairs
    # elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "TestCentre" && ($location eq "DS1_1" || $location eq "DS1_2" || $location eq "DS1_3"))
    # {
    #   $MainMessage_fr         = "Centre de pr&eacute;l&egrave;vement";
    #   $MainMessage_en         = "Test Centre";
    #   $subMessage_fr         = "<span style=\"background-color: #ff0000\">Si vous n'avez pas encore fait votre prélèvement sanguin, veuillez enregistr&eacute;r &agrave; la reception du Centre de pr&eacute;l&egrave;vement <b>en haut au rez de chauss&eacute;e</b>.</span> $Aptinfo_fr";
    #   $subMessage_en         = "<span style=\"background-color: #ff0000\">If you did not already have your blood test, please checkin at the Test Centre Reception</b> <b>upstairs on the ground floor</b>.</span> $Aptinfo_en";
    #   $log_message         = "$PatientId, $location, $subMessage_en";

    # }
    # destination is downstairs but checkin location is upstairs
    elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "DS1" && ($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3"))
    {
    #   $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
    #   $MainMessage_en         = "You are Checked In";
        $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
        $MainMessage_en         = "You are Checked In";
    #   $subMessage_fr         = "<span style=\"background-color: #ff0000\">Veuillez prendre place dans la salle d'attente <b>en bas au sous-sol</b>.</span> $Aptinfo_fr";
        $subMessage_fr         = "<span style=\"background-color: yellow\">Vous &ecirc;tes enregistr&eacute;. Veuillez sortir du Centre de cancer et attendre d'être appelé par SMS ou retourner 5 minutes avant votre rendez-vous.</span> $Aptinfo_fr";
    #   $subMessage_en         = "<span style=\"background-color: #ff0000\">Please have a seat in the waiting room <b>downstairs on level S1</b>.</span> $Aptinfo_en";
        $subMessage_en      = "<span style=\"background-color: yellow\">You are checked in. Please leave the Cancer Centre and wait to be called by SMS or come back only 5 minutes before your appointment time.</span> $Aptinfo_en";
      $log_message         = "$PatientId, $location, $subMessage_en";

    }
    # destination is OrthoWaitRoom and checkin location is Ortho_1 or Ortho_2
    # elsif($PhotoStatus == 1 && $DestinationWaitingRoom  eq "OrthoWaitRoom" && ($location eq "Ortho_1" || $location eq "Ortho_2"))
    # {
    #   $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
    #   $MainMessage_en         = "You are Checked In";
    #   #$MainMessage_fr         = "V&eacute;rifier &agrave la r&eacute;ception";
    #   #$MainMessage_en         = "Please go to the reception";
    #   #$subMessage_fr         = "Veuillez prendre place dans la salle d'attente. <span style=\"background-color: #ffff00\">Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.</span><br> $Aptinfo_fr";
    #   #$subMessage_en         = "Please have a seat in the waiting room. <span style=\"background-color: #ffff00\">Your name will appear on the TV screens when you are called.</span><br> $Aptinfo_en";
    #   $subMessage_fr         = "Veuillez vous pr&eacute;senter &agrave la <span style=\"background-color: #ffff00\">r&eacute;ception</span> pour compl&eacute;ter votre enregistrement.<br> $Aptinfo_fr";
    #   $subMessage_en         = "Please go to the <span style=\"background-color: #ffff00\">reception</span> to complete the check-in process.<br> $Aptinfo_en";
    #   $log_message         = "$PatientId, $location, $subMessage_en";

    # }
    # destination is RadiologyMGH and checkin location is Ortho_1 or Ortho_2
    # elsif($PilotStatus == 1 && $PhotoStatus == 1 && $DestinationWaitingRoom  eq "RadiologyMGH" && ($location eq "Ortho_1" || $location eq "Ortho_2"))
    # {
    #   $MainMessage_fr         = "Radiographie";
    #   $MainMessage_en         = "Go for X-ray";
    #   $subMessage_fr         = "<span style=\"background-color: #ff0000\">Veuillez-vous enregistrer au C5-163 pour la radiographie.</span> &Agrave votre retour, veuillez <u>repasser votre carte</u>. ";
    #   $subMessage_en         = "<span style=\"background-color: #ff0000\">Please register at C5-163 for an X-ray.</span> Afterwards, please come back here and <u>check-in again</u>. ";
    #   $log_message         = "$PatientId, $location, $subMessage_en";

    # }
    elsif($PilotStatus == 1 && $PhotoStatus == 1) # destination is same as checkin location
    {
      $MainMessage_fr         = "Vous &ecirc;tes enregistr&eacute;";
      $MainMessage_en         = "You are Checked In";
    #   $subMessage_fr         = "Veuillez prendre place dans la salle d'attente. Votre nom appara&icirc;tra sur les &eacute;crans lorsqu'il sera temps d'&ecirc;tre vu.<br> $Aptinfo_fr";
        $subMessage_fr         = "<span style=\"background-color: yellow\">Vous &ecirc;tes enregistr&eacute;. Veuillez sortir du Centre de cancer et attendre d'être appelé par SMS ou retourner 5 minutes avant votre rendez-vous.</span> $Aptinfo_fr";
    #   $subMessage_en         = "Please have a seat in the waiting room. Your name will appear on the TV screens when you are called.<br> $Aptinfo_en";
        $subMessage_en      = "<span style=\"background-color: yellow\">You are checked in. Please leave the Cancer Centre and wait to be called by SMS or come back only 5 minutes before your appointment time.</span> $Aptinfo_en";
      $log_message         = "$PatientId, $location, $subMessage_en";

      # Estimated waiting time option
      #$subMessage_fr         = "<b>Rendez-vous :</b> $Appt_type_fr<br><b>Horaire pr&eacutevu :</b> $ScheduledStartTime_fr <b>Horaire r&eacutevis&eacute :</b> $ScheduledStartTime_en<br><b>Temps d'attente estim&eacute :</b> <span style=\"background-color: #ffff00\">20 mins &agrave partir de maintenant</span><br><b>Message :</b> <span style=\"background-color: #FF6666\">bla bla bla</span>";

      #$subMessage_en         = "<b>Appointment:</b> $Appt_type_en<br><b>Scheduled:</b> $ScheduledStartTime_en<br><b>Expected:</b> <span style=\"background-color: #ffff00\">$ScheduledStartTime_en</span><br><b>Estimated wait:</b> <span style=\"background-color: #ffff00\">20 mins from now</span><br><b>Message :</b><span style=\"background-color: #FF6666\"> bla bla bla</span>";
    }
    elsif($PilotStatus == 1 && $PhotoStatus == 0) # No photo (Note, patient has successfully checked in)
    {
      # Tell the patient to go to the reception for photo
      #$message_bgcolor         = $darkgreen;
      $message_txtcolor     = $white;
      $MainMessage_fr         = "V&eacute;rifier &agrave la r&eacute;ception";
      $MainMessage_en         = "Please go to the reception";
      $subMessage_fr         = "Veuillez vous pr&eacute;senter &agrave la r&eacute;ception <span style=\"background-color: #FFFFE0\"><b><font color='red'><b>pour que l'on vous prenne en photo.</font></b></span>";
      $subMessage_en         = "Please go to the reception <span style=\"background-color: #FFFFE0\"><b><font color='red'>to have your photo taken.</font></b></span><b></b>";
      $middleMessage_fr     = "<b></b>";
      $middleMessage_en     = "<b></b>";
      $arrows             = 1;
      $DestinationWaitingRoom    = "reception";
      #$middleMessage_image     = "<img src=\"$images/reception_alone.png\">";
      $middleMessage_image     = "<img src=\"$images/Reception_generic.png\">";
      $middleMessage_image     = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

      # reload to the default page after 20 seconds
      $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

      $log_message         = "$PatientId, $location, $subMessage_en";
    }
    else # Not in the pilot, although successfully checked in
    {
      # Tell the patient to go to the reception
      #$message_bgcolor         = $darkgreen;
      $message_txtcolor     = $white;
      $MainMessage_fr         = "V&eacute;rifier &agrave la r&eacute;ception";
      $MainMessage_en         = "Please go to the reception";
      $subMessage_fr         = "Veuillez vous pr&eacute;senter &agrave la r&eacute;ception";
      $subMessage_en         = "Please go to the reception";
      $middleMessage_fr     = "<b></b>";
      $middleMessage_en     = "<b></b>";
      $arrows             = 1;
      $DestinationWaitingRoom    = "reception";
      #$middleMessage_image     = "<img src=\"$images/reception_alone.png\">";
      $middleMessage_image     = "<img src=\"$images/Reception_generic.png\">";
      $middleMessage_image     = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

      # reload to the default page after 20 seconds
      $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

      $log_message         = "$PatientId, $location, $subMessage_en";
    }
    $arrows             = 1;

    print "Waiting room symbol: $DestinationWaitingRoom<br>" if $verbose;

    if($DestinationWaitingRoom eq "DS1")
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      $middleMessage_image         = "<img src=\"$images/salle_DS1.png\">";
    }
    if($DestinationWaitingRoom eq "DRC")
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      #$middleMessage_image         = "<img src=\"$images/salle_DRC.png\">";
      $middleMessage_image         = "<img width=\"700\" src=\"$images/DRC_default.png\">";
    }

    if($DestinationWaitingRoom =~ m/TB_/i || $DestinationWaitingRoom =~ m/STX_/i)
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      $middleMessage_image         = "<img src=\"$images/$DestinationWaitingRoom.png\">";
    }

    if($DestinationWaitingRoom eq "TestCentre")
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      $middleMessage_image         = "<img src=\"$images/$DestinationWaitingRoom.png\">";
    }

    if($DestinationWaitingRoom eq "RadiologyMGH")
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      $middleMessage_image         = "<img width=\"614\" src=\"$images/$DestinationWaitingRoom.png\">";
    }
    if($DestinationWaitingRoom eq "OrthoWaitRoom")
    {
      $middleMessage_fr         = "";
      $middleMessage_en         = "";
      $middleMessage_image         = "<img width=\"614\" src=\"$images/$DestinationWaitingRoom.png\">";
    }

  }
  elsif( $CheckinStatus ne "OK" || !$Appt_type_en) # No appointment found - send to the reception
  {
    #$message_bgcolor         = $darkgreen;
    $message_txtcolor         = $white;
    $MainMessage_fr         = "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en         = "Please go to the reception";
    $subMessage_fr         = "Impossible de vous enregistrer en ce moment";
    $subMessage_en         = "Unable to check you in at this time";
    $middleMessage_fr         = "<b></b>";
    $middleMessage_en         = "<b></b>";
    $arrows             = 1;
    $DestinationWaitingRoom    = "reception";
    $middleMessage_image     = "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image     = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message         = "$PatientId, $location, $subMessage_en";
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
    #$message_bgcolor         = $darkgreen;
    $message_txtcolor         = $white;
    $MainMessage_fr         = "V&eacute;rifier &agrave la r&eacute;ception";
    $MainMessage_en         = "Please go to the reception";
    $subMessage_fr         = "Impossible de vous enregistrer en ce moment";
    $subMessage_en         = "Unable to check you in at this time";
    $middleMessage_fr         = "<b></b>";
    $middleMessage_en         = "<b></b>";
    $arrows             = 1;
    $DestinationWaitingRoom    = "reception";
    $middleMessage_image     = "<img src=\"$images/Reception_generic.png\">";
    $middleMessage_image     = "<img width=\"614\" src=\"$images/Reception_Ortho.png\">" if ($location eq "Ortho_1" || $location eq "Ortho_2");

    # reload to the default page after 20 seconds
    $reload = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"20\; URL=$checkin_script?location=$location&verbose=$verbose\">";

    $log_message         = "Problem detected - sending to reception - $PatientId, $location, $subMessage_en";


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
  my $whitespace         = "<font color = \"white\">space</font>";

  my $whitespace_verbose     = "<a href=\"$checkin_script?verbose=$verboselink\"><font color = \"$verboseColor\">space</font></a>";
  my $whitespace_DRC_1        = "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_1\"><font color = \"$verboseColor\">DRC_1</font></a>";
  my $whitespace_DRC_2        = "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_2\"><font color = \"$verboseColor\">DRC_2</font></a>";
  my $whitespace_DRC_3        = "<a href=\"$checkin_script?verbose=$verboselink&location=DRC_3\"><font color = \"$verboseColor\">DRC_3</font></a>";
  my $whitespace_DS1_1        = "<a href=\"$checkin_script?verbose=$verboselink&location=DS1_1\"><font color = \"$verboseColor\">DS1_1</font></a>";
  my $whitespace_DS1_2        = "<a href=\"$checkin_script?verbose=$verboselink&location=DS1_2\"><font color = \"$verboseColor\">DS1_2</font></a>";
  my $whitespace_DS1_3        = "<a href=\"$checkin_script?verbose=$verboselink&location=DS1_3\"><font color = \"$verboseColor\">DS1_3</font></a>";
  my $whitespace_Ortho_1        = "<a href=\"$checkin_script?verbose=$verboselink&location=Ortho_1\"><font color = \"$verboseColor\">Ortho_1</font></a>";
  my $whitespace_Ortho_2        = "<a href=\"$checkin_script?verbose=$verboselink&location=Ortho_2\"><font color = \"$verboseColor\">Ortho_2</font></a>";
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
    $direction = "right"     if $DestinationWaitingRoom eq "reception";
    $direction = "hereRC"     if $DestinationWaitingRoom eq "DRC";
    $direction = "down"     if $DestinationWaitingRoom eq "DS1";

    $direction = "down"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "down"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "hereRC"     if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DRC_2
  if($location eq "DRC_2")
  {
    $direction = "left"     if $DestinationWaitingRoom eq "reception";
    $direction = "hereRC"     if $DestinationWaitingRoom eq "DRC";
    $direction = "down"     if $DestinationWaitingRoom eq "DS1";

    $direction = "down"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "down"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "up_left"     if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DRC_3
  if($location eq "DRC_3")
  {
    $direction = "left"     if $DestinationWaitingRoom eq "reception";
    $direction = "left"     if $DestinationWaitingRoom eq "DRC";
    $direction = "down"     if $DestinationWaitingRoom eq "DS1";

    $direction = "down"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "down"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "down"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "right"     if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DS1_1
  if($location eq "DS1_1")
  {
    $direction = "up"         if $DestinationWaitingRoom eq "DRC";
    $direction = "here"     if $DestinationWaitingRoom eq "DS1";
    $direction = "right"     if $DestinationWaitingRoom eq "reception";
    $direction = "here"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "here"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "here"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "here"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "here"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "here"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "up"         if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = DS1_2
  if($location eq "DS1_2")
  {
    $direction = "up"         if $DestinationWaitingRoom eq "DRC";
    $direction = "left"     if $DestinationWaitingRoom eq "DS1";
    $direction = "left"     if $DestinationWaitingRoom eq "reception";
    $direction = "left"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "left"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "up"         if $DestinationWaitingRoom eq "TestCentre";
  }

# location = DS1_3
  if($location eq "DS1_3")
  {
    $direction = "up"         if $DestinationWaitingRoom eq "DRC";
    $direction = "left"     if $DestinationWaitingRoom eq "DS1";
    $direction = "left"     if $DestinationWaitingRoom eq "reception";
    $direction = "left"     if $DestinationWaitingRoom eq "STX_1";
    $direction = "left"     if $DestinationWaitingRoom eq "STX_2";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_3";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_4";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_5";
    $direction = "left"     if $DestinationWaitingRoom eq "TB_6";
    $direction = "up"         if $DestinationWaitingRoom eq "TestCentre";
  }

  # location = Ortho_1 (outside of Ortho clinic)
  if($location eq "Ortho_1")
  {
    $direction = "leftOrtho"     if $DestinationWaitingRoom eq "OrthoWaitRoom";
    $direction = "leftOrtho"     if $DestinationWaitingRoom eq "reception";
  }

  # location = Ortho_2 (inside Ortho clinic)
  if($location eq "Ortho_2")
  {
    $direction = "hereOrtho"     if $DestinationWaitingRoom eq "OrthoWaitRoom";
    $direction = "leftOrtho"     if $DestinationWaitingRoom eq "reception";
    $direction = "rightOrtho"     if $DestinationWaitingRoom eq "RadiologyMGH";
    $direction = "rightOrtho"     if $DestinationWaitingRoom eq "MGH_Admissions";
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

  print "      $direction_image" if $arrows;
  print "    </td>
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

  print "      $direction_image" if $arrows;
  print "    </td>
             <td rowspan=\"1\">$shortline</td>
    </tr>
      </table>
    <!---<font style=\"color: $white;\">--<font><br>    -->
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
# Find this patient
#------------------------------------------------------------------------
sub findPatient
{
  #------------------------------------------------------------------------
  # Hospital IDs are numeric only. If there are letters in the ID then it is
  # the ramq, so search by ramq
  #------------------------------------------------------------------------
  my $queryPatient = "";
  if($PatientId =~ /[a-zA-Z]/)
  {
    # the scanned barcode only contains LASFYYMMDDX
    print "Patient Ramq: $PatientId<br>" if $verbose;

    $PatientId = substr($PatientId,0,12);
    print "Patient Ramq, after truncation: $PatientId<br>" if $verbose;

    $queryPatient = "
        SELECT
            P.LastName,
            P.FirstName,
            P.PatientSerNum,
            PI.ExpirationDate
        FROM
            Patient P
            INNER JOIN PatientInsuranceIdentifier PI ON PI.PatientId = P.PatientSerNum
                AND PI.InsuranceNumber = '$PatientId'
            INNER JOIN Insurance I ON I.InsuranceId = PI.InsuranceId
                AND I.InsuranceCode = 'RAMQ'
    ";
  }
  else
  {
    print "Patient ID: $PatientId<br>" if $verbose;

    $queryPatient = "
        SELECT
            P.LastName,
            P.FirstName,
            P.PatientSerNum,
            COALESCE(PI.ExpirationDate,NULL) AS ExpirationDate
        FROM
            Patient P
            INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
                AND PH.MedicalRecordNumber = '$PatientId'
            INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
                AND H.HospitalCode = '$site'
            LEFT JOIN (
                SELECT
                    PI.PatientId,
                    PI.ExpirationDate
                FROM
                    PatientInsuranceIdentifier PI
                    INNER JOIN Insurance I ON I.InsuranceId = PI.InsuranceId
                        AND I.InsuranceCode = 'RAMQ'
            ) PI ON PI.PatientId = P.PatientSerNum
    ";
  }

  #============================================================================================
  # Retrieve data
  #============================================================================================
  my $sqlID;
  my $PatientSer     = "NULL"; # PatientSer is NULL until filled
  my $PatientSerNum     = "NULL"; # PatientSer is NULL until filled
  my $PatientLastName;
  my $PatientFirstName;
  my $PatientRamqExpiration;

  #------------------------------------------------------------------------
  # Database initialisation stuff
  #------------------------------------------------------------------------
  my $dbh_mysql = LoadConfigs::GetDatabaseConnection("ORMS") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database: \n\n";

  print "SQLID: $queryPatient>br>" if $verbose;

  my $query= $dbh_mysql->prepare($queryPatient)
    #or die "Couldn't prepare MySQL sqlID statement: " . $dbh_mysql->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't prepre MySQL sqlID statement: " . $dbh_mysql->errstr . "\n\n";

  $query->execute()
     #or die "Couldn't execute MySQL sqlID statement: " . $query->errstr;
     or print CHECKINLOG "###$now, $location ERROR - Couldn't execute MySQL sqlID statement: " . $query->errstr . "\n\n";

  my @data = $query->fetchrow_array();

  # grab data from MySQL
  $PatientLastName       = $data[0] if $data[0];
  $PatientFirstName      = $data[1] if $data[1];
  $PatientSerNum         = $data[2] if $data[2];
  $PatientRamqExpiration = $data[3] if $data[3];

  print "Patient LastName: $PatientLastName <br>" if $verbose;
  print "Patient FirstName: $PatientFirstName <br>" if $verbose;
  print "PatientSer in findPatient: $PatientSer <br>" if $verbose;
  print "PatientSerNum in findPatient: $PatientSerNum <br>" if $verbose;

  #######################################################################
  # Check that the Ramq has not expired
  #######################################################################
  my $RAMQCardExpired = 0;

  if($PatientRamqExpiration)
  {
    my $expiration = Time::Piece->strptime($PatientRamqExpiration,'%Y-%m-%d');

    $RAMQCardExpired = 1 if($expiration <= Time::Piece->new);
  }

  print "Is ramq expired? : $RAMQCardExpired<br>" if $verbose;


  #######################################################################

  # Set the patient's display name for the screen
  my $PatientDisplayName = "$PatientFirstName ". substr($PatientLastName,0,3) ."****";

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
  my ($PatientSer,$PatientSerNum,$startOfToday,$startOfToday_mysql,$endOfToday,$endOfToday_mysql) = @_;

  print "Checking in PatientSer  $PatientSer<br>" if $verbose;
  print "Checking in PatientSerNum  $PatientSerNum<br>" if $verbose;

  my $PhotoOk = 1; # ok by default
  my $PilotOk = 1; # ok by default - unless the patient has a non-pilot appointment it should be ok

  my $hasAriaAppointment = 0;

  my $dbh_mysql = LoadConfigs::GetDatabaseConnection("ORMS") or print CHECKINLOG "###$now, $location ERROR - Couldn't connect to database: \n\n";

  #============================================================================================
  # Determine the patient's ***NEXT*** appointment TODAY Get in ascending order so the very next
  # appointment is picked of the top
  #============================================================================================

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
  my @AptTimeHour;

  ##############################################################################################
  #                Appointment search                           #
  ##############################################################################################
  my $sqlAppt = "
      SELECT DISTINCT
        PH.MedicalRecordNumber,
        P.FirstName,
        P.LastName,
        MV.ScheduledDateTime,
        AC.AppointmentCode,
        CR.ResourceName,
        (UNIX_TIMESTAMP(MV.ScheduledDateTime)-UNIX_TIMESTAMP('$startOfToday_mysql'))/60 AS AptTimeSinceMidnight,
        MV.AppointmentSerNum,
        HOUR(MV.ScheduledDateTime),
        MV.AppointSys,
        MV.AppointId,
        CR.ResourceCode
    FROM
        Patient P
        INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = P.PatientSerNum
            AND MV.PatientSerNum = $PatientSerNum
            AND MV.ScheduledDateTime >= \'$startOfToday_mysql\'
            AND MV.ScheduledDateTime < \'$endOfToday_mysql\'
            AND MV.Status = 'Open'
        INNER JOIN ClinicResources CR ON CR.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
        INNER JOIN AppointmentCode AC ON AC.AppointmentCodeId = MV.AppointmentCodeId
        INNER JOIN PatientHospitalIdentifier PH ON PH.PatientId = P.PatientSerNum
        INNER JOIN Hospital H ON H.HospitalId = PH.HospitalId
            AND H.HospitalCode = '$site'
    ORDER BY MV.ScheduledDateTime
  ";

  print "sqlAppt: $sqlAppt<br>" if $verbose;

  my $query= $dbh_mysql->prepare($sqlAppt)
    #or die "Couldn't prepare sqlAppt statement: " . $dbh_mysql->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't prepare sqlAppt statement: " . $dbh_mysql->errstr . "\n\n";

  $query->execute()
    #or die "Couldn't execute sqlAppt statement: " . $query->errstr;
    or print CHECKINLOG "###$now, $location ERROR - Couldn't execute sqlAppt statement: " . $query->errstr . "\n\n";

  # set up arrays to hold the data pertaining to multiple appointments
  my @MV_PatientId;
  my @MV_PatientFirstName;
  my @MV_PatientLastName;
  my @MV_ScheduledStartTime;
  my @MV_ApptDescription;
  my @MV_Resource;
  my @MV_ResourceCode;
  my @MV_AptTimeSinceMidnight;
  my @MV_AppointmentSerNum;
  my @MV_Appt_type_en;
  my @MV_Appt_type_fr;
  my @MV_MachineId;
  my @MV_AptTimeHour;
  my @MV_AMorPM;
  my @MV_sys;
  my @MV_appId;

  print "<br>-------------Appointment Search---------------------------<br>" if $verbose;
  my $numAppts = 0;
  $MV_AptTimeSinceMidnight[0] = 999999999999999999999; # fail safe
  # lopp over all the appointments for this patient today
  # The NEXT appointment will be the first (ie the zeroth) in the list
  while(my @data = $query->fetchrow_array())
  {
    print "<br>-------------Appointment---------------------------<br>" if $verbose;
    # grab data from MySQL
    $MV_PatientId[$numAppts]        = $data[0];
    $MV_PatientFirstName[$numAppts]    = $data[1];
    $MV_PatientLastName[$numAppts]    = $data[2];
    $MV_ScheduledStartTime[$numAppts]    = $data[3];
    $MV_ApptDescription[$numAppts]    = $data[4]; ###
    $MV_Resource[$numAppts]        = $data[5];
    $MV_ResourceCode[$numAppts]    = $data[11];
    $MV_AptTimeSinceMidnight[$numAppts]= $data[6];
    $MV_AppointmentSerNum[$numAppts]    = $data[7];
    $MV_AptTimeHour[$numAppts]        = $data[8];
    $MV_sys[$numAppts]             = $data[9];
    $MV_appId[$numAppts]           = $data[10];

    # appointment AM or PM
    if($MV_AptTimeHour[$numAppts] >= 13)
    {
      $MV_AMorPM[$numAppts] = "PM";
    }
    else
    {
      $MV_AMorPM[$numAppts] = "AM";
    }

    print "MV_Patient LastName: $MV_PatientLastName[$numAppts] <br>" if $verbose;
    print "MV_ScheduledStartTime: $MV_ScheduledStartTime[$numAppts] <br>" if $verbose;
    print "MV_ApptDescription: $MV_ApptDescription[$numAppts]<br>" if $verbose;
    print "MV_Resource: $MV_Resource[$numAppts]<br>" if $verbose;
    print "MV_AptTimeSinceMidnight: $MV_AptTimeSinceMidnight[$numAppts]<br>" if $verbose;
    print "MV_AppointmentSerNum: $MV_AppointmentSerNum[$numAppts]<br>" if $verbose;
    print "MV_AptTimeHour: $MV_AptTimeHour[$numAppts]<br>" if $verbose;

    #------------------------------------------------------------------------
    # Is the appointment in the pilot? If yes, nothing to do. If no, then PilotOk = 0
    #------------------------------------------------------------------------
    my $Resource = $MV_Resource[$numAppts];
    #Uncomment below when ready to initiate use of Resources.pm module
    my $checkPilot = 1;

    #------------------------------------------------------------------------
    # Set the appointment type here - just appointment for now
    #------------------------------------------------------------------------
    $MV_Appt_type_en[$numAppts] = "Appointment";
    $MV_Appt_type_fr[$numAppts] = "rendez-vous";

    $numAppts++;
  } # end of appointment search

  # If we have no appointment today, return now with an error
  if( !$MV_AppointmentSerNum[0] )
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
  my $nextApptDescription;
  my $nextApptAMorPM;
  my $CheckInResource;
  my $checkInResourceCode;

  $nextApptAMorPM= $MV_AMorPM[0];
  $nextApptDescription = $MV_ApptDescription[0];
  $CheckInResource = $MV_Resource[0];
  $checkInResourceCode = $MV_ResourceCode[0];
  print "MV CheckInResource: $CheckInResource<br>" if $verbose;

  #------------------------------------------------------------------------
  # Figure out the appropriate waiting room for the NEXT appointment
  #------------------------------------------------------------------------
  my $CheckinVenue;
  my $CheckinVenueName;

  if($location eq "DRC_1" || $location eq "DRC_2" || $location eq "DRC_3")
  {
    $CheckinVenue = 8225;
    $CheckinVenueName = "D RC WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  elsif($location eq "DS1_1" || $location eq "DS1_2" || $location eq "DS1_3")
  {
    $CheckinVenue = 8226;
    $CheckinVenueName = "D S1 WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  # Orthopedics
  elsif($location eq "Ortho_1" || $location eq "Ortho_2")
  {
    $CheckinVenue = 0;
    $CheckinVenueName = "ORTHO WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;
  }
  elsif($location =~ /Reception/)
  {
    $CheckinVenue = 0;
    $CheckinVenueName = $location =~ s/ Reception//r;
    $CheckinVenueName = uc "$CheckinVenueName WAITING ROOM";
  }
  else
  {
    $CheckinVenue = 0;
    $CheckinVenueName = "UNKNOWN";
  }

  my $WaitingRoomWherePatientShouldWait;

  my $level;
  #$level = "unknown" if !$level;


  # Patients for whom the next appointment is a blood test, should be sent to the test centre
  # and also checked into the test centre
  if($CheckInResource eq "NS - prise de sang/blood tests pre/post tx")
  {
    print "******** This patient has a blood test first ****************<br>" if $verbose;

    # checking patient into test centre waiting room so that it is clear to all that they
    # are getting a blood test
    #$CheckinVenue = 8227;
    #$CheckinVenueName = "TEST CENTRE WAITING ROOM";
    print "Checkin venue is: $CheckinVenueName<br>" if $verbose;

    $WaitingRoomWherePatientShouldWait = "TestCentre";
  }

  ##########################################################################################
  # Check the patient in for all appointments
  # - loop over all appointments and check the patient in for each...
  ##########################################################################################
  my @MV_CheckinStatus;
  for(my $appointment = 0; $appointment < $numAppts; $appointment++)
  {
    print "<br>------------------------------------------------------------<br>"if $verbose;
    print "<br>Checking into appointment #$appointment <br>" if $verbose;
    #---------------------------------------------------------------------------------------------
    # First, check for an existing entry in the patient location table for this appointment
    #---------------------------------------------------------------------------------------------
    my $sqlMV_checkCheckin = "
      SELECT DISTINCT
        PatientLocationSerNum,
        PatientLocationRevCount,
        CheckinVenueName,
        ArrivalDateTime
    FROM
        PatientLocation
     WHERE
        AppointmentSerNum = $MV_AppointmentSerNum[$appointment]
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
    my $PatientLocationSerNum            = $data[0];
    my $PatientLocationRevCount            = $data[1];
    my $CheckinVenueNamePrevious            = $data[2]; # use the new venue, not the old
    my $ArrivalDateTime                = $data[3];

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

    print "Patient has been checked into appointment #$appointment... status: $MV_CheckinStatus[$appointment]" if $verbose;
    print "<center>Patient has been checked into appointment <i>$MV_Resource[$appointment]</i> at <b>$MV_ScheduledStartTime[$appointment]</b>... status: $MV_CheckinStatus[$appointment]<br></center>" if $verbose || $location =~ m/Reception/i;
    print "<br>===================== Finished checkin =====================<br>" if $verbose;

    #if the appointment originates from Aria, call the AriaIE to update the Aria db
    if($MV_sys[$appointment] eq "Aria" and $ariaCheckinUrl)
    {
        my $appId = $MV_appId[$appointment];

        local($SIG{__DIE__});
        my $request = HTTP::Request->new("POST",$ariaCheckinUrl);
        $request->header("Content-Type" => "application/json");
        $request->content("{\"room\":\"$CheckinVenueName\",\"sourceId\":\"$appId\",\"sourceSystem\":\"Aria\"}");

        $ua->request($request);

        $hasAriaAppointment = 1;

        $WaitingRoomWherePatientShouldWait = "DS1";
    }

  } # end of checkin

  #load the clinic schedules and see which floor the clinics are on today
  my $schedule = {};
  my $weekday = Time::Piece->new->fullday;
  my $amPM = Time::Piece->new->strftime("%P");

  if(-e dirname(__FILE__)."/../tmp/schedule.csv")
  {
      my $csv = Parse::CSV->new(
          file => dirname(__FILE__)."/../tmp/schedule.csv",
          sep_char => ",",
          names => 1 #fields are weekday, clinic Code, am, level
      );

      while(my $line = $csv->fetch) {
          my $clinicCode = $line->{"clinic code"};
          $WaitingRoomWherePatientShouldWait = $line->{"level"} if(
              $line->{"weekday"} eq $weekday
              && $checkInResourceCode =~ /$clinicCode/
              && $line->{"am"} eq $amPM
          );
      }
  }

  #check if the patient has an Aria photo
  if($hasAriaAppointment)
  {
    my $mrn = $MV_PatientId[0];

    local($SIG{__DIE__});
    my $request = HTTP::Request->new("POST",$ariaPhotoUrl);
    $request->header("Content-Type" => "application/json");
    $request->content("{\"mrn\":\"$mrn\",\"site\":\"$site\"}");

    my $photoResult = $ua->request($request);

    if($photoResult->is_success) {
        my $json = JSON->new->allow_nonref;
        $photoResult = eval { $json->decode($photoResult->content) };

        $PhotoOk = 0 if(defined $photoResult and !$photoResult->{"hasPhoto"} eq "1");
    }
  }


  # Set the return value
  @returnValue = ($MV_CheckinStatus[0],$MV_ScheduledStartTime[0],$MV_ScheduledStartTime[0],$MV_Appt_type_en[0],$MV_Appt_type_fr[0],$WaitingRoomWherePatientShouldWait,$MV_MachineId[0],$PhotoOk,$PilotOk);
  print "returnValue: @returnValue<br>" if $verbose;


  ##################################
  # The return value should just be for the NEXT appointment

  return @returnValue;

  ##################################

}

exit;
