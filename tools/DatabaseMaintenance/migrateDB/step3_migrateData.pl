#!/usr/bin/perl

#-----------------------------------------------
# Script that filters all patients whose primary mrn is not RVH
#-----------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------

use strict;
#use warnings;
use v5.26;

#use Cwd qw(abs_path);

use HTTP::Request::Common;
use LWP::UserAgent;
use XML::Hash::XS;
use File::JSON::Slurper qw(read_json write_json);
use Time::Piece;

use Data::Dumper;

use DBI;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Hash::XS->new();

my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request

#read input data
my $patients = read_json("./data/rvhPatients.json");

my $dbhOld = DBI->connect_cached("DBI:MariaDB:database=;host=",'','') or die("Can't connect");
my $dbhNew = DBI->connect_cached("DBI:MariaDB:database=;host=",'','') or die("Can't connect");
my $queries = prepareQueries($dbhOld,$dbhNew);

my $resources = getResourceList($dbhNew);

#migrate data
my $count = 0;

foreach my $ormsSer (keys $patients->%*)
{
    $count++;
    say $count if($count % 100 == 0);

    my $oacisData = fetchDataInAdt($patients->{$ormsSer}->{"oacisId"});

    #process some fields
    if($oacisData->{"ramqNumber"}) {
        $oacisData->{"ramqExpDate"} = Time::Piece->strptime($oacisData->{"ramqExpDate"} =~ s/T.+//r,'%Y-%m-%d')->strftime("%y%m");
    }
    else {
        $oacisData->{"ramqNumber"} = $patients->{$ormsSer}->{"mrn"};
        $oacisData->{"ramqExpDate"} = "0000";
    }

    my $ormsData = fetchDataInOrms($queries,$ormsSer);

    #migrate data
    $dbhNew->begin_work;

    $queries->{"insertPatient"}->execute(
        $oacisData->{"lastName"},
        $oacisData->{"firstName"},
        $oacisData->{"ramqNumber"},
        $oacisData->{"ramqExpDate"},
        $patients->{$ormsSer}->{"mrn"},
        $ormsData->{"SMSAlertNum"},
        $ormsData->{"SMSSignupDate"},
        $ormsData->{"OpalPatient"},
        $ormsData->{"LanguagePreference"},
        $ormsData->{"SMSLastUpdated"}
    ) or die("Query execution failed: ".$queries->{"insertPatient"}->errstr);

    #get the newly generated PatientSerNum
    my $newPatientSerNum = $dbhNew->{mariadb_insertid};

    for($ormsData->{"appointments"}->@*)
    {
        $queries->{"insertAppointment"}->execute(
            $newPatientSerNum,
            $_->{"Resource"},
            $_->{"ResourceDescription"},
            $resources->{$_->{"ResourceDescription"}},
            $_->{"ScheduledDateTime"},
            $_->{"ScheduledDate"},
            $_->{"ScheduledTime"},
            $_->{"AppointmentCode"},
            $_->{"AppointId"},
            $_->{"AppointId"},
            $_->{"AppointSys"},
            $_->{"Status"},
            $_->{"MedivisitStatus"},
            $_->{"CreationDate"},
            $_->{"ReferringPhysician"},
            $_->{"LastUpdated"}
        ) or die("Query execution failed: ".$queries->{"insertAppointment"}->errstr);

        my $newAppointmentSerNum = $dbhNew->{mariadb_insertid};

        for($_->{"locations"})
        {
            $queries->{"insertLocation"}->execute(
                $newAppointmentSerNum,
                $_->{"PatientLocationRevCount"},
                $_->{"CheckinVenueName"},
                $_->{"ArrivalDateTime"},
                $_->{"DichargeThisLocationDateTime"},
                $_->{"IntendedAppointmentFlag"}
            ) or die("Query execution failed: ".$queries->{"insertLocation"}->errstr);
        }
    }

    for($ormsData->{"weights"}->@*)
    {
        $queries->{"insertWeight"}->execute(
            $newPatientSerNum,
            $_->{"AppointmentId"},
            $_->{"PatientId"},
            $_->{"Date"},
            $_->{"Time"},
            $_->{"Height"},
            $_->{"Weight"},
            $_->{"BSA"},
            $_->{"LastUpdated"}
        ) or die("Query execution failed: ".$queries->{"insertWeight"}->errstr);
    }

    $dbhNew->commit;
}

####### subroutines ##########

sub fetchDataInAdt
{
    my ($oacisId) = @_;

    #search the ADT for the patient
    my $requestContent = "
        <soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/'>
            <soapenv:Header/>
            <soapenv:Body>
                <pds:getPatientByInternalId>
                    <internalId>$oacisId</internalId>
                </pds:getPatientByInternalId>
            </soapenv:Body>
        </soapenv:Envelope>";

    #make the request
    my $request = POST $requestLocation, Content_Type => $requestType, Content => $requestContent;
    my $response = $ua->request($request);

    #check if the request failed and return an empty array if it did
    return undef if(!$response->is_success());

    #parse the xml data into an array of hashes
    my $patient = $xml->xml2hash($response->content(),filter=> '/env:Envelope/env:Body/ns2:getPatientByInternalIdResponse/return',force_array=> ['mrns'])->[0];

    return $patient;
}

sub getResourceList
{
    my ($dbh) = @_;

    my $query = $dbh->prepare("
        SELECT
            ClinicResources.ResourceName,
            ClinicResources.ClinicResourcesSerNum
        FROM
            ClinicResources
    ") or die("Couldn't prepare statement: ". $dbh->errstr);
    $query->execute() or die("Couldn't execute statement: ". $query->errstr);

    my $rows = $query->fetchall_arrayref({});

    return {map { $_->{"ResourceName"} => $_->{"ClinicResourcesSerNum"} } $rows->@*};
}

sub prepareQueries
{
    my ($dbhOld,$dbhNew) = @_;

    my $queryPatientInfo = $dbhOld->prepare_cached("
        SELECT
            Patient.SMSAlertNum,
            Patient.SMSSignupDate,
            Patient.OpalPatient,
            Patient.LanguagePreference,
            Patient.SMSLastUpdated
        FROM
            Patient
        WHERE
            Patient.PatientSerNum = ?
    ") or die("Couldn't prepare statement: ". $dbhOld->errstr);

    my $queryAppointments = $dbhOld->prepare_cached("
        SELECT
            MV.AppointSerNum,
            MV.Resource,
            MV.ResourceDescription,
            MV.ScheduledDateTime,
            MV.ScheduledDate,
            MV.ScheduledTime,
            MV.AppointmentCode,
            MV.AppointId,
            CASE WHEN MV.AppointSys = 'InstantAddOn' THEN MV.AppointSys ELSE 'Medivisit' END AS AppointSys,
            MV.Status,
            MV.MedivisitStatus,
            MV.CreationDate,
            MV.ReferringPhysician,
            MV.LastUpdated
        FROM
            MediVisitAppointmentList MV
            INNER JOIN ClinicResources ON ClinicResources.ClinicResourcesSerNum = MV.ClinicResourcesSerNum
                AND ClinicResources.Speciality = 'Oncology'
        WHERE
            MV.PatientSerNum = ?
            AND MV.Resource != 'CV-SIGN'
        ORDER BY MV.ScheduledDateTime
    ") or die("Couldn't prepare statement: ". $dbhOld->errstr);

    my $queryLocations = $dbhOld->prepare_cached("
        SELECT
            PatientLocationMH.PatientLocationRevCount,
            CASE
                WHEN PatientLocationMH.CheckinVenueName = 'DRC Waiting Room' THEN 'D RC WAITING ROOM'
                WHEN PatientLocationMH.CheckinVenueName = 'RC Waiting Room' THEN 'D RC WAITING ROOM'
                WHEN PatientLocationMH.CheckinVenueName = 'DS1 Waiting Room' THEN 'D S1 WAITING ROOM'
                WHEN PatientLocationMH.CheckinVenueName = 'S1 Waiting Room' THEN 'D S1 WAITING ROOM'
                ELSE PatientLocationMH.CheckinVenueName
            END AS CheckinVenueName,
            PatientLocationMH.ArrivalDateTime,
            PatientLocationMH.DichargeThisLocationDateTime,
            PatientLocationMH.IntendedAppointmentFlag
        FROM
            PatientLocationMH
        WHERE
            PatientLocationMH.AppointSerNum = ?
            AND PatientLocationMH.CheckinVenueName NOT IN ('','unknown','Unspecified Waiting Room')
        ORDER BY PatientLocationMH.PatientLocationRevCount
    ") or die("Couldn't prepare statement: ". $dbhOld->errstr);

    my $queryWeights = $dbhOld->prepare_cached("
        SELECT
            PatientMeasurement.AppointmentId,
            PatientMeasurement.PatientId,
            PatientMeasurement.Date,
            PatientMeasurement.Time,
            PatientMeasurement.Height,
            PatientMeasurement.Weight,
            PatientMeasurement.BSA,
            PatientMeasurement.LastUpdated
        FROM
            PatientMeasurement
        WHERE
            PatientMeasurement.PatientSer = ?
    ") or die("Couldn't prepare statement: ". $dbhOld->errstr);

    my $insertPatient = $dbhNew->prepare_cached("
        INSERT INTO Patient(
            LastName,
            FirstName,
            SSN,
            SSNExpDate,
            PatientId,
            SMSAlertNum,
            SMSSignupDate,
            OpalPatient,
            LanguagePreference,
            SMSLastUpdated
        )
        VALUES(?,?,?,?,?,?,?,?,?,?)
    ") or die("Couldn't prepare statement: ". $dbhNew->errstr);

    my $insertAppointment = $dbhNew->prepare_cached("
        INSERT INTO MediVisitAppointmentList(
            PatientSerNum,
            Resource,
            ResourceDescription,
            ClinicResourcesSerNum,
            ScheduledDateTime,
            ScheduledDate,
            ScheduledTime,
            AppointmentCode,
            AppointId,
            AppointIdIn,
            AppointSys,
            Status,
            MedivisitStatus,
            CreationDate,
            ReferringPhysician,
            LastUpdated
        )
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ") or die("Couldn't prepare statement: ". $dbhNew->errstr);

    my $insertLocation = $dbhNew->prepare_cached("
        INSERT INTO PatientLocationMH(
           AppointmentSerNum,
           PatientLocationRevCount,
           CheckinVenueName,
           ArrivalDateTime,
           DichargeThisLocationDateTime,
           IntendedAppointmentFlag
        )
        VALUES(?,?,?,?,?,?)
    ") or die("Couldn't prepare statement: ". $dbhNew->errstr);

    my $insertWeight = $dbhNew->prepare_cached("
        INSERT INTO PatientMeasurement(
            PatientSerNum,
            AppointmentId,
            PatientId,
            Date,
            Time,
            Height,
            Weight,
            BSA,
            LastUpdated
        )
        VALUES(?,?,?,?,?,?,?,?,?,?)
    ") or die("Couldn't prepare statement: ". $dbhNew->errstr);

    return {
        "getPatientInfo" => $queryPatientInfo,
        "getAppointments" => $queryAppointments,
        "getLocations"  => $queryLocations,
        "getWeights" => $queryWeights,
        "insertPatient" => $insertPatient,
        "insertAppointment" => $insertAppointment,
        "insertLocation" => $insertLocation,
        "insertWeight" => $insertWeight
    }
}

sub fetchDataInOrms
{
    my ($queries,$ormsSer) = @_;

    my $patient = {};

    #get data from Patient table
    $queries->{"getPatientInfo"}->execute($ormsSer) or die("Couldn't execute statement: ". $queries->{"getPatientInfo"}->errstr);
    $patient = $queries->{"getPatientInfo"}->fetchall_arrayref({})->[0];

    #get data from appointment table
    $queries->{"getAppointments"}->execute($ormsSer) or die("Couldn't execute statement: ". $queries->{"getAppointments"}->errstr);
    $patient->{"appointments"} = $queries->{"getAppointments"}->fetchall_arrayref({});

    for($patient->{"appointments"})
    {
        $queries->{"getLocations"}->execute($_->{"AppointSerNum"}) or die("Couldn't execute statement: ". $queries->{"getLocations"}->errstr);
        $_->{"locations"} = $queries->{"getLocations"}->fetchall_arrayref({});
    }

    #get data from measurement table
    $queries->{"getWeights"}->execute($ormsSer) or die("Couldn't execute statement: ". $queries->{"getWeights"}->errstr);
    $patient->{"weights"} = $queries->{"getWeights"}->fetchall_arrayref({});

    return $patient;
}
