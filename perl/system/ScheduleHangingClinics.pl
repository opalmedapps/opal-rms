#!/opt/perl5/perl

#------------------------------------------------------------------------
# Perl script to schedule hanging clinics in ORMS
# User should first examine the list of Hanging clinics in the WaitRoomManagement DB
# and assign a speciality to each
# Then run this script to assign each hanging clinic to its default exam room and schedule
#-----------------------------------------------------------------------

#------------------------------------------------------------------------
# Declarations/initialisations
#------------------------------------------------------------------------
use strict;
use warnings;
use v5.30;
use lib "./modules";

#------------------------------------------------------------------------
# Load Modules
#------------------------------------------------------------------------
use LoadConfigs;

#------------------------------------------------------------------------
# Connect to the MUHC MySQL database
#------------------------------------------------------------------------
my $dbh = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database");

#------------------------------------------------------------------------
# Get the hanging clinics
#------------------------------------------------------------------------
my $sqlHangingClinics = "
	SELECT
		MediVisitResourceDes, 
		Speciality	
	FROM
		HangingClinics	
	WHERE
		Speciality IS NOT NULL";


my $query = $dbh->prepare($sqlHangingClinics) or die("Couldn't prepare statement: ". $dbh->errstr);

$query->execute() or die("Couldn't execute statement: ". $query->errstr);

my $mediVisitResourceDes;
my $numHangingClinics = 0;
my $speciality = 0;

while(my $data = $query->fetchrow_hashref())
{
	$mediVisitResourceDes = $data->{'MediVisitResourceDes'}; 
	$speciality = $data->{'Speciality'};

	say "Found hanging clinic: $mediVisitResourceDes [$speciality]";

	#------------------------------------------------------------------------
	# Get the default clinic/room for this resource
	#------------------------------------------------------------------------
	my $sqlDefault = "
    	SELECT
			ClinicScheduleSerNum	
		FROM
			ClinicSchedule	
		WHERE
			ClinicName LIKE ?";

	my $query = $dbh->prepare($sqlDefault) or die("Couldn't prepare statement: ". $dbh->errstr);

	$query->execute("%$speciality Default%") or die("Couldn't execute statement: ". $query->errstr);

	my $clinicScheduleSerNum = $query->fetchall_arrayref({})->[0]->{'ClinicScheduleSerNum'};

	#------------------------------------------------------------------------
	# Insert this hanging clinic into the schedule using the default for
	# its speciality
	#------------------------------------------------------------------------
	my $insertHangingClinic = $dbh->do("INSERT INTO ClinicResources(ResourceName,Speciality,ClinicScheduleSerNum) VALUES(?,?,?)",undef,$mediVisitResourceDes ,$speciality, $clinicScheduleSerNum) or die("Couldn't insert clinic in database: ". $dbh->errstr);

	say "Inserted $mediVisitResourceDes";

	#------------------------------------------------------------------------
	# Delete the now scheduled hanging clinic from the hanging clinics table
	#------------------------------------------------------------------------
	my $deleteHangingClinic = $dbh->do("DELETE FROM HangingClinics WHERE MediVisitResourceDes = ?",undef,$mediVisitResourceDes) or die("Couldn't insert clinic in database: ". $dbh->errstr); 

	say "Deleted $mediVisitResourceDes";

	$numHangingClinics++;
}

say "A total of $numHangingClinics hanging clinics were scheduled";

exit;

