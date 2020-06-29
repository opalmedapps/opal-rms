#!/usr/bin/perl
#---------------------------------------------------
# Package that loads configs for use in perl scripts
#---------------------------------------------------

package LoadConfigs;

use strict;
use v5.16;
use lib ".";

use Config::Tiny;
use DBI;

use Cwd qw(abs_path);

=begin
#load config depending on the git branch of the repository
#get current git branch
my $output = `git symbolic-ref -q HEAD 2>&1`;
chomp($output);

my $gitBranch = (split('/',$output))[-1]; #get the last section of the string to get the branch

#if on preprod or master branch, load configs for live use
#otherwise load the default dev configs

if($gitBranch eq 'preprod' or $gitBranch eq 'master')
{
	require("../config/perlLive.pl");
}
else
{
	require("configFileDev.pl");
}
=cut

#get the location of this package
#since we know the repository structure, we know where to look for the config file
my $currentLocation = abs_path(__FILE__ ."/../");

#load the config file
my $configLoader = Config::Tiny->new;
my $config = $configLoader->read("$currentLocation/../../../config/config.conf");

#returns a hash with all configs
sub LoadConfigs::getAllConfigs
{
	return $config;
}

#returns a hash with specific configs
sub LoadConfigs::GetConfigs
{
	my $section = shift;

	return $config->{$section};
}

#returns a db connection handle to a requested database server
#options are predefined as "ORMS"
#return 0 if connection fails
sub LoadConfigs::GetDatabaseConnection
{
	my $requestedConnection = shift;

	my $dbInfo = $config->{'database'};

	#set the inital value of the connection to 0 (failure value)
	#the requesting script can then determine what to do if the db fails to connect
	my $dbh = 0;

	#connects to WaitRoomManagment db by default
	if($requestedConnection eq 'ORMS')
	{
		$dbh = DBI->connect_cached("DBI:MariaDB:database=$dbInfo->{'ORMS_DB'};host=$dbInfo->{'ORMS_HOST'};port=$dbInfo->{'ORMS_PORT'}",$dbInfo->{'ORMS_USERNAME'},$dbInfo->{'ORMS_PASSWORD'}) or 0;
	}

	#logging db
	elsif($requestedConnection eq 'LOGS')
	{
		$dbh = DBI->connect_cached("DBI:MariaDB:database=$dbInfo->{'LOG_DB'};host=$dbInfo->{'LOG_HOST'};port=$dbInfo->{'LOG_PORT'}",$dbInfo->{'LOG_USERNAME'},$dbInfo->{'LOG_PASSWORD'}) or 0;
	}

	return $dbh;
}

1;
