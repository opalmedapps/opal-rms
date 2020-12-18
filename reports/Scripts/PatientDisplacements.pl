#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all appointment in the specified time range and outputs all patient checkins and venue movements
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use v5.26;

use lib '../../perl/system/modules';
use LoadConfigs;

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;
use Time::Piece;

#-----------------------------------------
#start html feedback
#-----------------------------------------
print CGI->new->header('application/json');

#------------------------------------------
#parse input parameters
#------------------------------------------
my $sDateInit = param("sDate");
my $eDateInit = param("eDate");
my $clinic = param("clinic");

my $sDate = $sDateInit ." 00:00:00";
my $eDate = $eDateInit ." 23:59:59";

my $specialityFilter = "AND ClinicResources.Speciality = 'Oncology' " if($clinic eq 'onc');
$specialityFilter = "AND ClinicResources.Speciality = 'Ortho' " if($clinic eq 'ortho');

#-----------------------------------------------------
#setup global variables
#-----------------------------------------------------
my @output; #array of objects containing appointment information

my $format = '%Y-%m-%d %H:%M:%S';

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get a list of check in/outs for patients who had an appointment in the specified date range
#this includes the PatientLocation table
my $sql = "
    SELECT
        MediVisitAppointmentList.ScheduledDate,
        MediVisitAppointmentList.AppointmentSerNum,
        Patient.PatientSerNum,
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId,
        PatientLocations.CheckinVenueName,
        PatientLocations.ArrivalDateTime,
        PatientLocations.DichargeThisLocationDateTime,
        PatientLocations.PatientLocationRevCount,
        MediVisitAppointmentList.ResourceDescription,
        MediVisitAppointmentList.AppointmentCode,
        MediVisitAppointmentList.Status,
        MediVisitAppointmentList.ScheduledTime
    FROM
        Patient
        INNER JOIN MediVisitAppointmentList ON MediVisitAppointmentList.PatientSerNum = Patient.PatientSerNum
            AND MediVisitAppointmentList.Status != 'Deleted'
            AND MediVisitAppointmentList.Status != 'Cancelled'
            AND MediVisitAppointmentList.ScheduledDate BETWEEN '$sDate' AND '$eDate'
        INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MediVisitAppointmentList.ClinicResourcesSerNum
            $specialityFilter
        LEFT JOIN (
            SELECT
                PatientLocationMH.CheckinVenueName,
                PatientLocationMH.ArrivalDateTime,
                PatientLocationMH.DichargeThisLocationDateTime,
                PatientLocationMH.PatientLocationRevCount,
                PatientLocationMH.AppointmentSerNum
            FROM
                PatientLocationMH
            UNION
            SELECT
                PatientLocation.CheckinVenueName,
                PatientLocation.ArrivalDateTime,
                'NOT CHECKED OUT' AS DichargeThisLocationDateTime,
                PatientLocation.PatientLocationRevCount,
                PatientLocation.AppointmentSerNum
            FROM
                PatientLocation
        ) AS PatientLocations ON PatientLocations.AppointmentSerNum = MediVisitAppointmentList.AppointmentSerNum
    WHERE
        Patient.PatientId != '9999996'";

my $query = $dbh->prepare_cached($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

#process the data
while(my $data = $query->fetchrow_hashref())
{
    my %appObj;

    $appObj{'PatientId'} = $data->{'PatientId'};
    $appObj{'FirstName'} = $data->{'FirstName'};
    $appObj{'LastName'} = $data->{'LastName'};
    $appObj{'ScheduledDate'} = $data->{'ScheduledDate'};
    $appObj{'ScheduledTime'} = $data->{'ScheduledTime'};
    $appObj{'AppointmentCode'} = $data->{'AppointmentCode'};
    $appObj{'Status'} = $data->{'Status'};
    $appObj{'Resource'} = $data->{'ResourceDescription'};
    $appObj{'Venue'} = $data->{'CheckinVenueName'};
    $appObj{'Arrival'} = $data->{'ArrivalDateTime'};
    $appObj{'Discharge'} = $data->{'DichargeThisLocationDateTime'};

    #calculate the time the patient spent in the room
    if($appObj{'Arrival'} and $appObj{'Discharge'} ne 'NOT CHECKED OUT')
    {
        my $checkIn = Time::Piece->strptime($appObj{'Arrival'},$format);
        my $checkOut = Time::Piece->strptime($appObj{'Discharge'},$format);

        #this is in seconds, we want it in hours
        my $waitTime = $checkOut - $checkIn;
        $waitTime = sprintf("%0.2f",$waitTime/3600);

        $appObj{'WaitTime'} = $waitTime;
    }

    #sometimes the venue is null (a bug) so in that case rename the room
    $appObj{'Venue'} = "The Blank Room" if(!$appObj{'Venue'} and $appObj{'Arrival'});

    push @output, \%appObj;
}

my $json = JSON->new->allow_nonref;

say $json->encode(\@output);

exit;
