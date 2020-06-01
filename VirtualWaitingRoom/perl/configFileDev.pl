#!/opt/perl5/perl
#----------------------------------------
# dev perl config file
#----------------------------------------
use strict;
use v5.30;
use lib "./";

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use JSON;

use Cwd qw(abs_path);

#get the root orms folder
our $BASEPATH = abs_path($0."/../../");

#set up some useful objects
our $JSON = JSON->new;
our $CGI = CGI->new;

#set up database configs
our $WRM_DB = "DBI:MariaDB:database=WaitRoomManagement;host=172.26.125.194;port=3306";
our $WRM_USER = "ormsadm";
our $WRM_PASS = "aklw3hrq3asdf923k";

our $LOG_DB = "DBI:MariaDB:database=OrmsLog;host=172.26.125.194;port=3306";
our $LOG_TABLE = "VirtualWaitingRoomLog";
our $LOG_USER = "ormsadm";
our $LOG_PASS = "aklw3hrq3asdf923k";

#determine if weight documents should be sent
our $sendDocument = 0;

#initialize logging function
sub LOG_MESSAGE
{
	my $identifier = $_[0];
	my $type = $_[1];
	my $message = $_[2];

	my ($package,$filename,$line) = caller;

	$filename =~ s{.*/}{}; #remove path from the filename

	my $encodedArgs = $JSON->encode({filename=> $filename,identifier=> $identifier,type=> $type,message=> $message});

	system("./logMessage.pl '$encodedArgs' 1"); #add a second argument so that the log script knows to use system arguments and not cgi params
}

1;
