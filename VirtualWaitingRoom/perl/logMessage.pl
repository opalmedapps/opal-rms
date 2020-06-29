#!/usr/bin/perl
#----------------------------------------------
# Script to log a webpage input to a log file
#---------------------------------------------

#----------------------------------------
# import modules
#----------------------------------------
use strict;
use v5.16;
use lib "./";

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Time::Piece;
use DBI;

#------------------------------------------
# load configs
#------------------------------------------
our $BASEPATH; #base ORMS folder
our $JSON;
our $CGI;
our ($LOG_DB,$LOG_USER,$LOG_PASS,$LOG_TABLE); #logging database details

require "loadConfigs.pl";

my $now = localtime;
$now = $now->strftime("%Y-%m-%d %H:%M:%S");

#------------------------------------------
# parse input parameters
#------------------------------------------
#inputs will either be url parameters or they will be arguments passed as json in the script call

my ($filename,$identifier,$type,$message,$printJSON);

if($ARGV[1] eq 1) #this means that the script was called from another perl script
{
	my %input = %{$JSON->decode($ARGV[0])};

	$filename = $input{"filename"};
	$identifier = $input{"identifier"};
	$type = $input{"type"};
	$message = $input{"message"};
}
else
{
	$filename = param("filename");
	$identifier = param("identifier");
	$type = param("type");
	$message = param("message");
	$printJSON = param("printJSON");
}

if($printJSON)
{
	print $CGI->header('application/json');
}

#-----------------------------------------------------
# connect to database and log message
#-----------------------------------------------------
my $dbh =  DBI->connect_cached($LOG_DB,$LOG_USER,$LOG_PASS) or die("Couldn't connect to database: ".DBI->errstr);

my $sql = "
	INSERT INTO $LOG_TABLE (DateTime,FileName,Identifier,Type,Message)
	VALUES (?,?,?,?,?)";

my $query = $dbh->do($sql,undef,($now,$filename,$identifier,$type,$message)) or die("Query could not be prepared: ".$dbh->errstr);

$dbh->disconnect;

exit;
