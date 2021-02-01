#!/opt/perl5/perl
#----------------------------------------
# live perl config file
#----------------------------------------
use strict;
use v5.26;
use lib "./";
use lib "../../perl/system/modules";

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use JSON;
use Cwd qw(abs_path);

use LoadConfigs;

#load configs;
my $configs = LoadConfigs::getAllConfigs();

#get the root orms folder
our $BASEPATH = $configs->{"path"}->{"BASE_PATH"} ."/VirtualWaitingRoom";

#set up some useful objects
our $JSON = JSON->new;
our $CGI = CGI->new;

#set up database configs
our $WRM_DB = "DBI:MariaDB:database=". $configs->{"database"}{"ORMS_DB"} .";host=". $configs->{"database"}{"ORMS_HOST"} .";port=". $configs->{"database"}{"ORMS_PORT"};
our $WRM_USER = $configs->{"database"}{"ORMS_USERNAME"};
our $WRM_PASS = $configs->{"database"}{"ORMS_PASSWORD"};

our $LOG_DB = "DBI:MariaDB:database=". $configs->{"database"}{"LOG_DB"} .";host=". $configs->{"database"}{"LOG_HOST"} .";port=". $configs->{"database"}{"LOG_PORT"};
our $LOG_USER = $configs->{"database"}{"LOG_USERNAME"};
our $LOG_PASS = $configs->{"database"}{"LOG_PASSWORD"};
our $LOG_TABLE = "VirtualWaitingRoomLog";

#determine if weight documents should be sent
our $sendDocument = $configs->{"vwr"}->{"SEND_WEIGHTS"};

#initialize logging function
sub LOG_MESSAGE
{
    my $identifier = $_[0];
    my $type = $_[1];
    my $message = $_[2];

    my ($package,$filename,$line) = caller;
    $filename =~ s{.*/}{}; #remove path from the filename

    my $now = localtime;
    $now = $now->strftime("%Y-%m-%d %H:%M:%S");

    my $dbh =  DBI->connect_cached($LOG_DB,$LOG_USER,$LOG_PASS) or die("Couldn't connect to database: ".DBI->errstr);

    my $sql = "
        INSERT INTO $LOG_TABLE (DateTime,FileName,Identifier,Type,Message)
        VALUES (?,?,?,?,?)
    ";

    my $query = $dbh->do($sql,undef,($now,$filename,$identifier,$type,$message)) or die("Query could not be prepared: ".$dbh->errstr);

    $dbh->disconnect;
}

1;
