#!/opt/perl5/perl
#------------------------------------------------------------------------
# J.Kildea 14 Dec 2016
#------------------------------------------------------------------------
# PERL-CGI script to search through the WaitingRoomManegement database
# and extract the chemo time in chair
#
# Input parameters: 	date range
#		 	Fiscal period

#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
use strict;
use v5.30;

use lib '../../perl/system/modules';
use LoadConfigs;

#use warnings;
#use diagnostics;
use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Date::Calc;
#use Date::Calc qw( Standard_to_Business Today Business_to_Standard );
use Date::Calc qw(Decode_Month Today Now Decode_Date_US Today_and_Now Delta_DHMS);

#------------------------------------------------------------------------
# Some variables
#------------------------------------------------------------------------
my $padding = 0; #cellpadding in the table that is returned
my $numpatients_color = "black";
my $numTotal = 0;
my $numTrial = 0;
my $numFlagged = 0;
my $numSpecified = 0;

#------------------------------------------------------------------------
# Parse the input parameters
#------------------------------------------------------------------------
my $verbose	= param("verbose");
#$verbose	= 1;
my $sDateInit = param("sDate");
my $eDateInit = param("eDate");

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

#2016-03-05 23:59:59"

my $period = param("period");

#------------------------------------------------------------------------
# Start the webpage feedback
#------------------------------------------------------------------------
print "Content-type: text/html\n\n";
print "<title>MUHC Cedars Cancer Oncology Database Reports</title>\n";
print "<body>\n";
print "<center><h1>MUHC Department of Medical Physics</h1></center>";
print "<center><h2><u>ODC Time in Chair Report</u></h2></center>";
my @today_and_now = Today_and_Now();
print "<b>Time of report:</b> @today_and_now<br>\n";
print "<p>\n";

print "<b> Start Date:</b> $sDate <br>\n";
print "<b> End Date:</b> $eDate<br>\n";
print "<b> Period: </b> $period<br\n";

print "<p>\n";

#------------------------------------------------------------------------
# Prepare and submit the query
#------------------------------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

=begin
my $sql = "
SELECT RangeVal, COUNT( * )
FROM (
  SELECT t . * , (
    CASE
      WHEN TIMESTAMPDIFF( HOUR , `ArrivalDateTime` , `DichargeThisLocationDateTime` ) <2
        THEN '0-2'
      WHEN TIMESTAMPDIFF( HOUR , `ArrivalDateTime` , `DichargeThisLocationDateTime` ) >=2
        AND TIMESTAMPDIFF( HOUR , `ArrivalDateTime` , `DichargeThisLocationDateTime` ) <4
        THEN '2-4'
      ELSE '>4'
    END
  ) AS RangeVal
  FROM
       PatientLocationMH t,
       MediVisitAppointmentList
  WHERE
  t.CheckinVenueName LIKE \"%AREA%\"
  AND ArrivalDateTime >= \"$sDate\"
  AND ArrivalDateTime <= \"$eDate\"
  AND MediVisitAppointmentList.Resource IN (\"TXCHM\", \"TX-V\", \"BCG-TX-V\")
  AND MediVisitAppointmentList.AppointmentSerNum = t.AppointmentSerNum
)test
GROUP BY RangeVal
  ";
=cut

my $sql = "
	SELECT
		Ranges, COUNT(*)
	FROM (
		SELECT
			MV.AppointmentSerNum,
			(CASE
	      		WHEN TIMESTAMPDIFF(HOUR,MIN(PL.ArrivalDateTime),MAX(PL.DichargeThisLocationDateTime)) < 2 THEN '0-2'
	      		WHEN TIMESTAMPDIFF(HOUR,MIN(PL.ArrivalDateTime),MAX(PL.DichargeThisLocationDateTime)) >= 2
				AND TIMESTAMPDIFF(HOUR,MIN(PL.ArrivalDateTime),MAX(PL.DichargeThisLocationDateTime)) < 4 THEN '2-4'
	     		ELSE '>= 4'
	    		END) AS Ranges
	 	FROM
			Patient,
			MediVisitAppointmentList MV,
			PatientLocationMH PL
	  	WHERE
			Patient.PatientSerNum = MV.PatientSerNum
			AND MV.AppointmentSerNum = PL.AppointmentSerNum
			AND PL.CheckinVenueName LIKE '%AREA%'
			AND MV.ScheduledDate >= '$sDate'
			AND MV.ScheduledDate <= '$eDate'
			AND MV.Resource IN ('TXCHM', 'TX-V', 'BCG-TX-V')
			AND (Patient.LastName != 'ABCD' AND Patient.FirstName != 'Test Patient')
			AND Patient.LastName != 'Opal IGNORER SVP'
		GROUP BY MV.AppointmentSerNum) AS App
	GROUP BY Ranges";

print "SQL: $sql<br>" if $verbose;

my $query= $dbh->prepare($sql) or die "Couldn't prepare statement: " . $dbh->errstr;
$query->execute() or die "Couldn't execute statement: " . $query->errstr;

print "<table border=1>";
print "<tr><td>Time Range (hours)</td><td>Number of Patients</td></tr>";

# Examine the data returned
while(my @data = $query->fetchrow_array())
{
    my $RangeVal	= $data[0];
    my $Count		= $data[1];

    print "<tr><td>$RangeVal</td><td>$Count</td></tr>";
}
print "</table>";

#print "=================================================================<br>\n";


#print "<b>QUERY COMPLETE</b>\n";


#------------------------------------------------------------------------
# Some end notes
#------------------------------------------------------------------------
print "</table><p>\n";


# Fill in the missing javascript info

print "<hr>\n";
print "<center> Problems and bug reports to John Kildea<br> (john.kildea\@mcgill.ca)</center>\n";

print "<center> <a href=\"../../../Documents/oncology/oncology.html\">Report Central - Oncology Reports</a></center>\n";


#------------------------------------------------------------------------
# exit gently
#------------------------------------------------------------------------
print "</body>\n\n</HTML>";
exit;
