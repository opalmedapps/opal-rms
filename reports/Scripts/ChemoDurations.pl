#!/opt/perl5/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all the chemo appointments in specified date range and calculates the time spent inside the first "TX AREA" room the patient was checked in to.
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use v5.30;

use lib '../../perl/system/modules';
use LoadConfigs;

use Time::Piece;
use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;

#-----------------------------------------
#start html feedback
#-----------------------------------------
my $cgi = CGI->new;
print $cgi->header('application/json');

#------------------------------------------
#parse input parameters
#------------------------------------------
my $sDateInit = param("sDate");
my $eDateInit = param("eDate");

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get a list of all chemotherapy appointments in the date range
my $sql = "
	SELECT DISTINCT
		Patient.LastName,
		Patient.FirstName,
		Patient.PatientId,
		MV.Resource,
		MV.ResourceDescription,
		MV.AppointmentCode,
		MV.ScheduledDateTime,
		MV.Status,
		PL.CheckinVenueName,
		PL.ArrivalDateTime,
		PL.DichargeThisLocationDateTime,
		TIMEDIFF(PL.DichargeThisLocationDateTime,PL.ArrivalDateTime) AS Duration,
		PL.PatientLocationRevCount
	FROM
		Patient
		INNER JOIN MediVisitAppointmentList MV ON MV.PatientSerNum = Patient.PatientSerNum
			AND MV.Status = 'Completed'
			AND MV.AppointmentCode LIKE '%CHM%'
			AND MV.ScheduledDateTime BETWEEN '$sDate' AND '$eDate'
		INNER JOIN PatientLocationMH PL ON PL.AppointmentSerNum = MV.AppointmentSerNum
			AND PL.PatientLocationRevCount = (
				SELECT MIN(PatientLocationMH.PatientLocationRevCount)
				FROM PatientLocationMH
				WHERE
					PatientLocationMH.AppointmentSerNum = MV.AppointmentSerNum
					AND PatientLocationMH.CheckinVenueName LIKE '%TX AREA%'
			)
	WHERE
		Patient.PatientId NOT IN ('9999994','9999995','9999996','9999997','9999998','CCCC')
		AND Patient.PatientId NOT LIKE 'Opal%'
	ORDER BY MV.ScheduledDateTime, Patient.PatientId";

my $query = $dbh->prepare_cached($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

my @treatments;

#----------------------------------------
#process data
#----------------------------------------
while(my $data = $query->fetchrow_hashref())
{
	my %data = %{$data};

	push @treatments, \%data;
}

#----------------------------------------
#output json
#----------------------------------------
#convert the hash data into a json string
my $json = JSON->new->allow_nonref;

print $json->encode(\@treatments);

exit;
