#!/opt/perl5/perl
#--------------------------------------------------
# Loads the appropriate config file depending on the git branch of the repository
#--------------------------------------------------
use strict;
use v5.30;
use lib "./";

#get current git branch
my $output = `git symbolic-ref -q HEAD 2>&1`;
chomp($output);

my $gitBranch = (split('/',$output))[-1]; #get the last section of the string to get the branch

#if on preprod or master branch, load configs for live use
#otherwise load the default dev configs

if($gitBranch eq 'testing' or $gitBranch eq 'master' or $gitBranch eq 'aria15')
{
	require("configFileLive.pl");
}
else
{
	require("configFileDev.pl");
}

1;

