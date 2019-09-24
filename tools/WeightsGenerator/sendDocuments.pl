#!/usr/bin/perl
#----------------------------------------------
# Script to create a pdf that contains a list of weights for a patient and to send that document to Oacis through ATS
#---------------------------------------------

#----------------------------------------
# import modules
#----------------------------------------
use strict;
use v5.14;

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Time::Piece;
use DBI;
use Net::FTP;
use Net::FTPSSL;

my $facility = "RV";
my $aptsChart = "FMU-4183";

open(OUT,">./log_file.txt") or die("file does not exist or may has permissions problems");

#----------------------------------------------------
# setup the FTP necessary to transfer files
#----------------------------------------------------
my $connectionMessage;

#interface engine
#our $ftpConnection = Net::FTP->new("qdxengine.muhc.mcgill.ca","SSL"=> 1) or die("Could not create ftp object");
#say $connectionMessage = $ftpConnection->message;
#LOG_MESSAGE('connect_ftp','General',"Connected to interface engine server for patient $patientIdRVH with message: $connectionMessage");

#$ftpConnection->login("ariarpt","qdxt1X0") or die($ftpConnection->last_message);
#say $connectionMessage = $ftpConnection->message;
#LOG_MESSAGE('login_ftp','General',"Logged in to interface engine server for patient $patientIdRVH with message: $connectionMessage");

#dev interface engine
#our $ftpConnection = Net::FTP->new("172.26.191.134") or die("Could not create ftp object");
#$ftpConnection->login("ariarpt","qdxt1X0") or die($ftpConnection->last_message);

#prod
our $ftpConnection = Net::FTPSSL->new("172.26.188.167") or die("Could not create ftp object");
say OUT $connectionMessage = $ftpConnection->message;
#LOG_MESSAGE('connect_ftp','General',"Connected to prod server for patient $patientIdRVH with message: $connectionMessage");

$ftpConnection->login("wnetvmap29\\ProdImport","Importap29") or die($ftpConnection->last_message);
say OUT $connectionMessage = $ftpConnection->message;
#LOG_MESSAGE('login_ftp','General',"Logged in to prod server for patient $patientIdRVH with message: $connectionMessage");


#dev
#our $ftpConnection = Net::FTPSSL->new("172.26.188.198") or die("Could not create ftp object");
#$ftpConnection->login("wnetvmap08\\StrmImport","Importap08") or die($ftpConnection->last_message);

$ftpConnection->binary(); #necessary (vital) for some reason

#get all files
my @files = glob("./docs/*.pdf");

foreach my $pdfFile (@files)
{
	#parse the patientId
	(my $noZeroesId = $pdfFile) =~ s/_.+//;
	($noZeroesId = $noZeroesId) =~ s/\.\/docs\///;
	($noZeroesId = $noZeroesId) =~ s/0*(\d+)/$1/; #remove all leading zeroes in the patient id

	#next if($noZeroesId eq '9999996');
	#next unless($noZeroesId eq '142');

	say OUT $pdfFile;
	say OUT $noZeroesId;

	my $outputXMLFile = "./docs/MUHC-$facility-$noZeroesId-$aptsChart^Aria.xml";
	say OUT $outputXMLFile;

	#send the pdf to the ATS server
	$ftpConnection->put($pdfFile,"\\Aria\\MUHC-$facility-$noZeroesId-$aptsChart^Aria.001");
	say OUT $connectionMessage = $ftpConnection->message;
	#LOG_MESSAGE('send_pdf','General',"Sent weight pdf for patient $patientIdRVH to server with message: $connectionMessage");

	#copy the xml file to the ATS server 
	$ftpConnection->put($outputXMLFile,"\\Aria\\MUHC-$facility-$noZeroesId-$aptsChart^Aria.xml");
	say OUT $connectionMessage = $ftpConnection->message;
	#LOG_MESSAGE('send_xml','General',"Sent weight xml for patient $patientIdRVH to server with message: $connectionMessage");
	say $pdfFile;
}

say OUT "Weight documents sent";

#----------------------------------------
#disconnect from database and end script
#----------------------------------------
exit;
