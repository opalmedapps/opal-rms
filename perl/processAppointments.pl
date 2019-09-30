#!/opt/perl5/perl
#---------------------------------------------------------------------------------------------------------------
# Script that parses input and inserts/updates an appointment in the ORMS db
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use warnings;
use v5.30;
use lib "/var/www/OnlineRoomManagementSystem/perl/system/modules";

use LoadConfigs;
use Time::Piece;
use File::stat;
use File::Copy;
use File::Spec;
use MIME::Lite;
use Getopt::Long qw(:config no_auto_abbrev pass_through);
use Text::CSV;
use List::MoreUtils 'none';
use CGI::Simple;
use JSON;

#--------------------------------------------------------------
# Initialize variables
#--------------------------------------------------------------
my $cgi = CGI::Simple->new;
my $json = JSON->new->allow_nonref;

my $logPath = LoadConfigs::GetConfigs('path')->{'LOG_PATH'};
open(my $logFile,">>","$logPath/appointmentInsertLog.txt") or die('Could not open log file');

#change directory to be able to run insert script
chdir("./system");

#-------------------------------------------------------
# based on the parameters passed to the script, create a list of appointments to be processed
#-------------------------------------------------------

#appointment parameters
my $appointmentInfo = {
	action=> undef,
	code=> undef,
	creationDate=> undef,
	firstName=> undef,
	id=> undef,
	lastName=> undef,
	patientId=> undef,
	referringMd=> undef,
	resource=> undef,
	resourceDesc=> undef,
	scheduledDate=> undef,
	scheduledTime=> undef,
	site=> undef,
	ssn=> undef,
	ssnExpDate=> undef,
	status=> undef,
	system=> undef,
	type=> undef
};

#check where the script call came from
#if undefined, the call came from the command line
my $origin = $cgi->request_method() || "CL";

#ignore GET and HEAD requests
if($origin =~ /GET|HEAD/) 
{
	print $cgi->header("application/json");
	print $json->encode("forbidden");
	exit;
}
elsif($origin eq 'POST')
{
	print $cgi->header("application/json");

	#depending on the request content type, we have to parse the POST differently
	my $postParams = {};

	if($ENV{CONTENT_TYPE} eq "application/x-www-form-urlencoded")
	{
		$postParams->{$_} = $cgi->param($_) for($cgi->param());
	}
	elsif($ENV{CONTENT_TYPE} eq "application/json;charset=UTF-8")
	{
		$postParams = $json->decode($cgi->param('POSTDATA'));
	}

	#POST param names different from appointment parameters
	#%appointmentInfo = map{ $_ => [param($_)] } $cgi->param();
	#$appointmentInfo->{$_} = $cgi->param($_) for(keys $appointmentInfo->%*);

	#empty fields have a space inserted so that the fields are sent in the post request
	#remove the space
	for(values $postParams->%*) {$_ = '' if($_ eq ' ');}

	$appointmentInfo->{'action'} = $postParams->{'Action'};
	$appointmentInfo->{'code'} = $postParams->{'AppointCode'};
	$appointmentInfo->{'creationDate'} = $postParams->{'CreationDate'};
	$appointmentInfo->{'firstName'} = $postParams->{'PatFirstName'};
	$appointmentInfo->{'id'} = $postParams->{'AppointId'} || $postParams->{'VisitId'};
	$appointmentInfo->{'lastName'} = $postParams->{'PatLastName'};
	$appointmentInfo->{'patientId'} = $postParams->{'PatientId'};
	$appointmentInfo->{'referringMd'} = $postParams->{'ReferringMd'};
	$appointmentInfo->{'resource'} = $postParams->{'ResourceCode'};
	$appointmentInfo->{'resourceDesc'} = $postParams->{'ResourceName'};
	$appointmentInfo->{'scheduledDate'} = $postParams->{'AppointDate'};
	$appointmentInfo->{'scheduledTime'} = $postParams->{'AppointTime'};
	$appointmentInfo->{'site'} = $postParams->{'Site'};
	$appointmentInfo->{'ssn'} = $postParams->{'Ramq'};
	$appointmentInfo->{'ssnExpDate'} = $postParams->{'RamqExpireDate'};
	$appointmentInfo->{'status'} = $postParams->{'Status'};
	$appointmentInfo->{'system'} = $postParams->{'AppointSys'};
	$appointmentInfo->{'type'} = ($postParams->{'AppointId'}) ? "Appointment" : "Visit AddOn";

	#if the appointment info is complete, insert it in the database
	my $result = (isComplete($appointmentInfo)) ? insertInDatabase($appointmentInfo) : "Incomplete web request arguments";
	logResult($appointmentInfo,$postParams,$result);
	print $json->encode($result);
	exit;
}
#otherwise, the call came from the command line
elsif($origin eq "CL")
{
	my $args = {};
	GetOptions($args,
		'action:s',
		'id:s',
		'code:s',
		'creationDate:s',
		'firstName:s',
		'lastName:s',
		'patientId:s',
		'referringMd:s',
		'resource:s',
		'resourceDesc:s',
		'scheduledDate:s',
		'scheduledTime:s',
		'site:s',
		'ssn:s',
		'ssnExpDate:s',
		'status:s',
		'system:s',

		-'file:s'
	);

	#if a csv file was given to the script, open the csv and process every line
	if($args->{'file'})
	{
		#we're assuming the csv file will be encoded in utf8;
		use utf8;

		open(my $csvFile,"<","$args->{'file'}") or die('Could not open csv file');

		#csv file must have been created today, otherwise send an error
		#however, if its the weekend, don't send an error
		my $csvModTime = localtime(stat($csvFile)->mtime)->ymd;
		my $today = localtime;

		if($csvModTime ne $today->ymd)
		{
			logResult({},{file=>$args->{'file'}},"CSV file was not updated today") if($today->wdayname !~ /Sat|Sun/);
			exit;
		}

		#copy the file to the archive

		my $archiveFile = $logPath ."/CCCAppsListArchive/". $today->ymd .File::Spec->splitpath($args->{'file'});
		copy($args->{'file'},$archiveFile) or die('copy failed');

		#parse the csv file
		my $parser = Text::CSV->new({binary => 1, sep_char => ','});

		#get the headers with the first line and store the position of each column's name
		my @headerRow = @{$parser->getline($csvFile)};

		my $headers = {};
		while(my ($index,$value) = each @headerRow)
		{
			$headers->{$value} = $index;
		}


		while(my @row = $parser->getline($csvFile))
		{
			my $csvParams = {};
			$csvParams->{$_} = $row[$headers->{$_}] for (keys $headers->%*);

			my $appointmentInfo = {
				action=> "ADD",
				code=> $csvParams->{'Activity Code'},
				creationDate=> $csvParams->{'DH Cré RV'},
				firstName=> $csvParams->{'First Name'},
				id=> $csvParams->{'AppIDComb'},
				lastName=> $csvParams->{'Last name'},
				patientId=> $csvParams->{'MRN'},
				referringMd=> $csvParams->{'Md Référant RV'},
				resource=> $csvParams->{'Resource'},
				resourceDesc=> $csvParams->{'Resource Des'},
				scheduledDate=> $csvParams->{'App Date'},
				scheduledTime=> $csvParams->{'App Time'},
				site=> $csvParams->{'Site'},
				ssn=> $csvParams->{'RAMQ'},
				ssnExpDate=> $csvParams->{'Date Exp RAMQ'},
				status=> "Open",
				system=> "Impromptu"
			};

			#if the appointment info is complete, insert it in the database; otherwise log an error for the entry
			my $result = (isComplete($appointmentInfo)) ? insertInDatabase($appointmentInfo) : "Incomplete csv row information";
			logResult($appointmentInfo,$csvParams,$result);
		}
		exit;
	}
	#if appointment information was given instead, process it
	else
	{
		$appointmentInfo->{$_} = $args->{$_} for(keys $appointmentInfo->%*);

		#if the appointment info is complete, insert it in the database
		my $result = (isComplete($appointmentInfo)) ? insertInDatabase($appointmentInfo) : "Incomplete command line arguments";

		logResult($appointmentInfo,$result);
		exit;
	}
}

sub isComplete
{
	my $app = shift; #hash ref

	for(keys $app->%*)
	{
		return 0 if(!defined $app->{$_});
	}

	return 1;
}

sub insertInDatabase
{
	my $app = shift; #hash ref

	#sanitize the inputs and make sure they are correct
	my ($sanitizedApp,$sanitizeResult) = sanitizeAppointment($app);

	if($sanitizeResult eq "Sanitized")
	{
		my $functionCall = "./CreateAppointmentInOrms.pl ";
		$functionCall .= "-$_=\"$sanitizedApp->{$_}\" " for(sort keys $sanitizedApp->%*);
		$functionCall .= "-caller=\"". $cgi->remote_addr() ."\" ";

		my $insertResult = `$functionCall` || "UNDEFINED ERROR";

		return $insertResult;
	}
	else
	{
		return $sanitizeResult;
	}
}

#logs the result of the appointment insert and the parameters the script was called with
sub logResult
{
	my $appointInfo = shift; #hash ref
	my $requestInfo = shift; #hash ref
	my $message = shift; #str

	$_ = (defined $_) ? $_ : '' for(values $requestInfo->%*);

	my $time = localtime;
	$time = "${\$time->ymd} ${\$time->hms}";

	my $params = '';
	$params .= "$_: $requestInfo->{$_} |" for (sort keys $requestInfo->%*);

	say $logFile "[$time] result: $message; params: $params";

	#send an email if there was an error
	#ignore some messages
	my @ignoredMessages = (
		'success',
		'Appointment already exists',
		'Incomplete web request arguments',
		'Can\'t delete - Appointment does not exist'
	);
	#my @ignoredMessages = ('success','Appointment already completed');
	sendEmail($message,$time,$appointInfo) if(none {$message eq $_} @ignoredMessages);
}

sub sanitizeAppointment
{
	my $app->%* = shift->%*; #hash ref

	#perfrom some sanitization
	for(values $app->%*)
	{
		$_ =~ s/\\//g; #remove backslashes
		#$_ =~ s/'/\\'/g; #escape quotes
		$_ =~ s/"//g; #remove double quotes
		$_ =~ s/\n|\r//g; #remove new lines and tabs
		$_ =~ s/\s+/ /g; #remove multiple spaces
		$_ =~ s/\s$//g; #remove space at the end
	}

	#verify action
	return (undef,"Unknown action") unless($app->{'action'} =~ /ADD|UPD|DEL/);

	#uppercase names
	$app->{'lastName'} = uc $app->{'lastName'};
	$app->{'firstName'} = uc $app->{'firstName'};

	#resource and resource description cannot be empty
	return (undef,"Empty resource code or description") unless($app->{'resource'} and $app->{'resourceDesc'});

	#insert zeros for incomplete MRNs
	while(length($app->{'patientId'}) < 7)
	{
		$app->{'patientId'} = '0'. $app->{'patientId'};
	}

	#make sure date and time are in the right format
	my $dateSplitter = ($app->{'scheduledDate'} =~ /-/) ? "-" : "/";

	my ($yearS,$monthS,$dayS) = split($dateSplitter,$app->{'scheduledDate'});
	my ($yearC,$monthC,$dayC) = split($dateSplitter,$app->{'creationDate'});
	my ($hourS,$minuteS) = split(":",$app->{'scheduledTime'});

	$_ = $_ ? $_ : "" for($yearS,$monthS,$dayS,$yearC,$monthC,$dayC,$hourS,$minuteS);

	return (undef,"Incorrect date format for scheduledDate") unless($yearS =~ /\d\d\d\d/ and $monthS =~ /\d\d/ and $dayS =~ /\d\d/);
	return (undef,"Incorrect date format for creationDate") unless($yearC =~ /\d\d\d\d/ and $monthC =~ /\d\d/ and $dayC =~ /\d\d/);
	return (undef,"Incorrect time format for scheduledTime") unless($hourS =~ /\d\d/ and $minuteS =~ /\d\d/);

	#make sure the appointment id that we'll use in the orms system is in the correct format
	#visit: 8 digits
	#appointment: YYYYA + 8 digits
	#cancelled appointment: YYYYC + 7 digits

	return (undef,"Incorrect appointment ID format") unless($app->{'id'} =~ /^[0-9]{4}A[0-9]{8}/ or $app->{'id'} =~ /^[0-9]{4}C[0-9]{7}/ or $app->{'id'} =~ /^[0-9]{8}/ or $app->{'id'} eq "InstantAddOn");

	#other possible systems are group visits; 999999999G88888888 :  Group Appointment ID  (Appointment sequential#  G  Patient sequential number )  Ex: 20560969G4224207
	#eClinibase; 9999999E : Eclinibase appointment / visit id Ex: 1373791E
	#currently not used

	#current supported sites are RVH and MGH
	$app->{'site'} = "RVH" if($app->{'site'} eq 'V');
	$app->{'site'} = "MGH" if($app->{'site'} eq 'G');
	return (undef,"Site is not supported") unless($app->{'site'} eq "RVH" or $app->{'site'} eq "MGH");
	
	#all clear
	return ($app,"Sanitized");
}

#sends an email to the administrator
sub sendEmail
{
	my $subject = shift; #str
	my $time = shift; #str
	my $appointInfo = shift; #hash ref

	#sent a different message depending on the message
	#my $emailData;

	#if($subject eq "Incomplete command line arguments") {$emailData = "Error: '$subject' at [$time]";}
	#elsif($subject eq "Incomplete web request arguments") {}
	#elsif($subject eq "CSV file was not updated today") {$emailData = "Error: '$subject' at [$time]";}

	$subject = "ORMS Appointment Error - ". $subject;
	my $emailData = "Error: $subject for appointment ID: $appointInfo->{'id'} at time [$time]";

	my $mime = MIME::Lite->new (
		From=> "orms\@muhc.mcgill.ca",
		To=> "victor.matassa\@muhc.mcgill.ca",
		Type=> "text/plain",
		Subject=> $subject,
		Data=> $emailData
	);

	#send out email
	$mime->send('smtp', '172.25.123.208');
}