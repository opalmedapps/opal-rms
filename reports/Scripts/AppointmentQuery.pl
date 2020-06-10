#!/usr/bin/perl
#---------------------------------------------------------------------------------------------------------------
# This script finds all appointments matching the specified criteria and returns patient information from the phpmyadmin WaitRoomManagment database.
#---------------------------------------------------------------------------------------------------------------

#----------------------------------------
#import modules
#----------------------------------------
use strict;
use v5.26;

use lib '../../perl/system/modules';
use HospitalADT;
use LoadConfigs;

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);
use JSON;
use Time::Piece;
use Encode;

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
my $sTime = param("sTime");
my $eTime = param("eTime");
my $comp = param("comp");
my $open = param("openn");
my $prog = param('prog');
my $canc = param("canc");
my $arrived = param("arrived");
my $notArrived = param("notArrived");
my $clinic = param("clinic");
my $appType = param("type");
my $specificType = param("specificType");
my $updateRamq = param("updateRamq");

my $sDate = $sDateInit ." $sTime";
my $eDate = $eDateInit ." $eTime";

my $appFilter = "";
$appFilter .= "AND ( ";
$appFilter .= "MV.Status = 'Completed' OR " if($comp);
$appFilter .= "MV.Status = 'Open' OR " if($open);
$appFilter .= "MV.Status = 'Cancelled' OR " if($canc);
$appFilter .= "MV.Status = 'In Progress' OR " if($prog);
chop($appFilter) for (1..4);
$appFilter .=  ") ";

($specificType = $specificType) =~ s/'/\'/g;

my $specialityFilter = "AND CR.Speciality = '$clinic' ";

#escape any ' characters we find
($specificType = $specificType) =~ s/'/\\'/g;

my $typeFilter = "" if($appType eq 'all');
$typeFilter = "AND MV.ResourceDescription = '$specificType' " if($appType eq 'specific');

# print $appFilter . "\n\n";
# print $typeFilter . "\n\n";

#setup global variables
#-----------------------------------------------------
#the appointment serial will be used as the key
my %fname;
my %lname;
my %pID;
my %ssn;
my %ssnExp; #ssn expiry date
my %app; #appointment description
my %appType; #appointment type
my %appCode; #appointment code
my %appStatus; #appointment status
my %appTime; #scheduled time of appointment
my %appDay; #day of the appointment
my %creationDate; #creation date of the appointment
my %referringPhysician; #referring physician of the appointment
my %checkin; #time of patient checkin
my %mediStatus; #status of appointment in medivisit

my $format = '%Y-%m-%d %H:%M:%S';

my $jstring = {};

#-----------------------------------------------------
#connect to database and run queries
#-----------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");

#get the list of possible appointments and their resources
my $sql0 = "
	SELECT DISTINCT
		MV.ResourceDescription,
		MV.Resource
	FROM
		MediVisitAppointmentList MV,
		ClinicResources CR
	WHERE
		MV.ClinicResourcesSerNum = CR.ClinicResourcesSerNum
		$specialityFilter
	ORDER BY MV.ResourceDescription,MV.Resource";

 #print $sql0 . "\n\n";

my $query0 = $dbh->prepare_cached($sql0) or die("Query could not be prepared: ".$dbh->errstr);
$query0->execute() or die("Query execution failed: ".$query0->errstr);

my $clinics = [];
my %rDesc;

while(my @data0 = $query0->fetchrow_array()) {push @{$rDesc{$data0[0]}}, $data0[1];}

foreach my $desc (sort keys %rDesc)
{
	if(scalar @{$rDesc{$desc}} > 1)
	{
		unshift @{$rDesc{$desc}}, 'All';
	}

	#remove new lines
	$desc =~ s/\n|\r//g;

    push $clinics->@*, {name=> $desc, resources=> $rDesc{$desc}->@*};
}

$jstring->{"clinics"} = $clinics;
$jstring->{"tableData"} = [];

my $sql1 = "
	SELECT
		MV.AppointmentSerNum,
		Patient.FirstName,
		Patient.LastName,
		Patient.PatientId,
		Patient.SSN,
		Patient.SSNExpDate,
		MV.ResourceDescription,
		MV.Resource,
		MV.AppointmentCode,
		MV.Status,
		MV.ScheduledDate AS ScheduledDate,
		MV.ScheduledTime AS ScheduledTime,
		MV.CreationDate,
		MV.ReferringPhysician,
		(select PL.ArrivalDateTime from PatientLocation PL where PL.AppointmentSerNum = MV.AppointmentSerNum AND PL.PatientLocationRevCount = 1 limit 1) as ArrivalDateTime,
		(select PLM.ArrivalDateTime from PatientLocationMH PLM where PLM.AppointmentSerNum = MV.AppointmentSerNum AND PLM.PatientLocationRevCount = 1 limit 1) as ArrivalDateTime,
		MV.MedivisitStatus
	FROM
		Patient,
		MediVisitAppointmentList MV
	WHERE
		Patient.PatientSerNum = MV.PatientSerNum
		AND Patient.PatientId != '9999996'
		AND Patient.PatientId != '9999998'
		AND MV.ResourceDescription in (Select distinct CR.ResourceName from ClinicResources CR Where trim(CR.ResourceName) not in ('', 'null') $specialityFilter)
		$appFilter
		AND MV.Status != 'Deleted'
		AND MV.ResourceDescription NOT LIKE '%blood%'
		AND MV.ScheduledDateTime BETWEEN '$sDate' AND '$eDate'
		$typeFilter
	ORDER BY ScheduledDate,ScheduledTime";

my $query1 = $dbh->prepare_cached($sql1) or die("Query could not be prepared: ".$dbh->errstr);
$query1->execute() or die("Query execution failed: ".$query1->errstr);

while(my @data1 = $query1->fetchrow_array())
{
	my $ser = $data1[0];
	$fname{$ser} = $data1[1];
	$lname{$ser} = $data1[2];
	$pID{$ser} = $data1[3];
	$ssn{$ser} = $data1[4];
	$ssnExp{$ser} = $data1[5];
	$ssnExp{$ser} = "0$ssnExp{$ser}" if(length($ssnExp{$ser}) eq 3);
	$app{$ser} = $data1[6];
	$appType{$ser} = $data1[7];
	$appCode{$ser} = $data1[8];
	$appStatus{$ser} = $data1[9];
	$appDay{$ser} = $data1[10];
	$appTime{$ser} = substr($data1[11],0,-3);
	$creationDate{$ser} = $data1[12];
	$referringPhysician{$ser} = $data1[13];
	$checkin{$ser} = $data1[14];
	$checkin{$ser} = $data1[15] if($data1[15]);
	$mediStatus{$ser} = $data1[16];

	#check if the ssn is expired
	my $expired = 1;

	#get information from the hospital ADT for the ramq if enabled
	#also update expired ramqs if any are found
	if($updateRamq)
	{
		my $currentDate  = localtime;

		#check if the ramq is still valid before proceeding
		#this is to reduce computation time
		if(length($ssnExp{$ser}) eq 4 and substr($ssnExp{$ser},-2) <= 12)
		{
			my $expTP = Time::Piece->strptime("20$ssnExp{$ser}","%Y%m");
			$expTP = $expTP + Time::Piece->ONE_MONTH;

			$expired = 0 if($expTP > $currentDate);
		}

		if($expired eq 1)
		{
			my $ramqInfo = HospitalADT->getRamqInformation($ssn{$ser});

			if($ramqInfo->{'Status'} =~ /Valid/)
			{
				$expired = 0;

				#also make sure the ramq in the WRM db is up to date if the ramq is expired in the db
				HospitalADT->updateRamqInWRM($ssn{$ser});
			}
		}
	}

	#set 'today' as the appointment's scheduled date so that all verifications are done as if it were the day of the appointment
	my $today = Time::Piece->strptime($appDay{$ser},'%Y-%m-%d') if($appDay{$ser});

	#check if the appointment was created today
	my $createdToday = 0;
	if($creationDate{$ser} and $creationDate{$ser} ne '0000-00-00' and $today)
	{
		my $creationTP = Time::Piece->strptime($creationDate{$ser},"%Y-%m-%d");

		$createdToday = 1 if($creationTP eq $today);
	}

	#encode the information in json format
	if(($arrived and !$notArrived and $checkin{$ser})
		or (!$arrived and $notArrived and !$checkin{$ser})
		or ($arrived and $notArrived))
	{
		#you can see that some variables appear to be in the wrong place (like appType: $appCode)
		#this is because the physicians name things different from the DB column names
        my $appObj = {
            fname=> $fname{$ser},
            lname=> $lname{$ser},
            pID=> $pID{$ser},
            ssn=> {num=> $ssn{$ser},
            expDate=> $ssnExp{$ser},
            expired=> $expired},
            appName=> $app{$ser},
            appClinic=> $appType{$ser},
            appType=> $appCode{$ser},
            appStatus=> $appStatus{$ser},
            appDay=> $appDay{$ser},
            appTime=> $appTime{$ser},
            checkin=> $checkin{$ser},
            createdToday=> $createdToday,
            referringPhysician=> $referringPhysician{$ser},
            mediStatus=> $mediStatus{$ser}
        };

        #$_ = Encode::decode('utf8',$_) for(values $appObj->%*);
		push $jstring->{"tableData"}->@*, $appObj;
	}
}

my $json = JSON->new->ascii->allow_nonref;
my $output = $json->encode($jstring);
say $output;

exit;
