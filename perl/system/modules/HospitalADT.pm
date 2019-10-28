#!/opt/perl5/perl

package HospitalADT;

use strict;
#use warnings;
use v5.30;
use lib ".";

####################################################
# Package that gets patient data from the hospital ADT and returns information
####################################################

#load modules
use DBI;
use Time::Piece;
use HTTP::Request::Common;
use LWP::UserAgent;
use XML::Simple;

use LoadConfigs;

#prepare the agent to send the webservice request
my $ua = LWP::UserAgent->new();

#prepare an xml parser
my $xml = XML::Simple->new();

#set up database variables
my $dbhWRM;
my $queryGetExpiration;
my $queryUpdateExpiration;

#---------------------------------------------------------------------------------------------
#function that returns information about an ramq
#---------------------------------------------------------------------------------------------
# input: ramq string
# output = {
#	Status => 'Expired','Valid', or 'Error'
#	Message => return message
#	Expiration  => the latest expiration date of the specified ramq if a patient was found
#	Mrns => comma seperated string containing all patient mrns; format: ID1 (Hospital1), ID2 (Hospital2), ...
#	Ramq => ramq given to the function
# }
sub getRamqInformation
{
	my $self = $_[0];
	my $ramq = $_[1];

	my %returnObject; #stores the informaton collected on the ramq

	my $requestLocation = 'http://172.26.119.94:8080/pds/pds?wsdl'; #where to send the xml request
	my $requestType = 'text/xml; charset=utf-8'; # character encoding of the request
	my $requestContent = #the ADT request message
		"<soapenv:Envelope soap:encodingStyle='http://schemas.xmlsoap.org/soap/encoding/' xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:pds='http://pds.cis.muhc.mcgill.ca/' xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/' xmlns:soapenc='http://schemas.xmlsoap.org/soap/encoding/' xmlns:xsd='http://www.w3.org/2001/XMLSchema' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'>
			<soapenv:Body>
				<pds:findByRamq>
					<ramqs>$ramq</ramqs>
				</pds:findByRamq>
			</soapenv:Body>
		</soapenv:Envelope>";

	my $response = $ua->post($requestLocation, Content_Type => $requestType, Content => $requestContent);  #make the request and get the response back

	#check if the request failed and exit if it did
	if(!$response->is_success()) 
	{
		%returnObject = (Status=> 'Error', Message=> "Error code -10: Connection failed", Ramq=> $ramq);
		_LOG_MESSAGE("-10",$returnObject{'Status'},$returnObject{'Message'});

		return \%returnObject;
	}

	#parse the xml data into a perl readable format
	my %data = %{$xml->XMLin($response->content())};
	my $data = $data{'env:Body'}{'ns2:findByRamqResponse'}{'return'}; #the reference

	#check if the response is empty (no patient associated with the ramq), there is one match (a hash), or multiple matches (an array)
	if(!ref($data))
	{
		%returnObject = (Status=> 'Error', Message=> "Error code -1: No patient associated with ramq $ramq");
	}
	elsif(ref($data) eq 'HASH')
	{
		my %info = %{$data};

		#get the expiration date of the ramq and check if it is expired
		my $expiration = Time::Piece->strptime(substr($info{'ramqExpDate'},0,10),'%Y-%m-%d');

		if($expiration <= Time::Piece->new->add_months(-1))  #ramqs last until the end of the month they expire on
		{
			%returnObject = (Status=> 'Expired', Message=> "ramq $ramq expired on $expiration", Expiration=> $expiration->strftime('%Y-%d-%m'), Mrns=> _getMrns($info{'mrns'}));
		}
		else
		{
			%returnObject = (Status=> 'Valid', Message=> "ramq $ramq not expired", Expiration=> $expiration->strftime('%Y-%d-%m'), Mrns=> _getMrns($info{'mrns'}));
		}
	}
	if(ref($data) eq 'ARRAY')
	{
		#if multiple patients have the same RAMQ, then probably one of those ramqs is very old (since ramqs are supposed to be unique)
		#since RAMQs last between 4 to 8 years, we can hardcode the fact that any ramq with an expiration date before the year 2000 can be ignored -> not implemented
		my @patients = @{$data};
		my $dateLimit = Time::Piece->new->add_months(-1); #ramqs last until the end of the month they expire on

		foreach my $patient (@patients)
		{
			my %pat = %{$patient};

			#for each entry, check if the ramq is expired
			if(Time::Piece->strptime(substr($pat{'ramqExpDate'},0,10),'%Y-%m-%d') <= $dateLimit)
			{
				$patient = '';
			}
		}

		#filter the entries with expired ramqs
		@patients = grep{$_ ne ''} @patients;

		if(scalar(@patients) > 1) 
		{
			%returnObject = (Status=> 'Error', Message=> "Error code -2: Multiple patient matches for ramq $ramq!");
		}
		elsif(scalar(@patients) eq 0) 
		{
			%returnObject = (Status=> 'Error', Message=> "Error code -3: No specific patient associated with ramq $ramq");
		}
		elsif(scalar(@patients) eq 1)
		{
			my %info = %{$patients[0]};
			my $expiration = Time::Piece->strptime(substr($info{'ramqExpDate'},0,10),'%Y-%m-%d');
	
			%returnObject = (Status=> 'Valid', Message=> "ramq $ramq not expired", Expiration=> $expiration->strftime('%Y-%d-%m'), Mrns=> _getMrns($info{'mrns'}));
		}
	}

	#if the return object doesn't have a status, some possibility wasn't considered
	if(!$returnObject{'Status'})
	{
		%returnObject = (Status=> 'Error', Message=> 'Error code -20: Unspecified error');
	}

	$returnObject{'Ramq'} = $ramq;

	#log the final result in a database for troubleshooting purposes
	_LOG_MESSAGE('ADT',$returnObject{'Status'},$returnObject{'Message'});

	return \%returnObject;
}

#function that returns the mrns of patient as a string
#format: ID1 (Hospital1), ID2 (Hospital2), ...
sub _getMrns
{
	my $mrns = $_[0];

	my $mrnString = ''; #store all mrns in here

	#patients can have multiple mrns if they are patients of multiple hospitals so we check the reference type and look through all their mrns
	if(ref($mrns) eq 'HASH') #one mrn
	{
		$mrnString .= "$mrns->{'mrn'} ($mrns->{'mrnType'})"; 
	}
	elsif(ref($mrns) eq 'ARRAY') #multiple mrns
	{
		foreach my $mrnEntry (@{$mrns})
		{
			$mrnString .= "$mrnEntry->{'mrn'} ($mrnEntry->{'mrnType'}),";
		}
		$mrnString =~s/,$//; #remove trailing comma
	}

	return $mrnString;
}

#checks the WaitRoomManagement database if a specific ramq is expired, and if it is, updates the ramq with the current one from the ADT
sub updateRamqInWRM
{
	my $self = $_[0];
	my $ramq = $_[1];

	my $info = getRamqInformation($self,$ramq);

	#if the ramq is expired in the ADT, there's no point updating the WRM db
	if($info->{'Status'} eq 'Expired')
	{
		_LOG_MESSAGE("A","General","Ramq still expired for $ramq");
		return 'Ramq still expired';
	}
	elsif($info->{'Status'} eq 'Error')
	{
		_LOG_MESSAGE("B","General","Unable to update RAMQ; $info->{'Message'}");
		return "Unable to update RAMQ; $info->{'Message'}";
	}
	
	#connect to the WRM db and setup queries
	if(not defined $dbhWRM)
	{
		$dbhWRM = LoadConfigs::GetDatabaseConnection("ORMS") or die("Couldn't connect to database: ");
	}

	my $sqlGetExpiration = "
		SELECT
			Patient.SSNExpDate
		FROM
			Patient
		WHERE
			Patient.SSN = ?";

	#only prepare the queries once incase the function is being run in a loop
	if(not defined $queryGetExpiration)
	{
		$queryGetExpiration = $dbhWRM->prepare_cached($sqlGetExpiration) or die("Query could not be prepared: ".$dbhWRM->errstr);
	}
	
	my $sqlUpdateExpiration = "
		UPDATE Patient
		SET Patient.SSNExpDate = ?
		WHERE Patient.SSN = ?";

	if(not defined $queryUpdateExpiration)
	{
		$queryUpdateExpiration = $dbhWRM->prepare_cached($sqlUpdateExpiration) or die("Query could not be prepared: ".$dbhWRM->errstr);
	}

	#get the patient's ramq expiration date in the WRM db
	$queryGetExpiration->execute($ramq) or die("Query execution failed: ".$queryGetExpiration->errstr);

	my $oldExpiration = $queryGetExpiration->fetchall_arrayref({})->[0]->{'SSNExpDate'}; #format is yymm

	my $oldExpirationTP = Time::Piece->strptime($oldExpiration,'%y%m') if(length($oldExpiration) eq 4);
	my $currentExpirationTP = Time::Piece->strptime($info->{'Expiration'},'%Y-%d-%m');

	#if the ramq in the WRM does not match the ramq from the ADT, update the WRM
	if($oldExpirationTP != $currentExpirationTP)
	{
		$queryUpdateExpiration->execute($currentExpirationTP->strftime('%y%m'),$ramq) or die("Update execution failed: ".$queryUpdateExpiration->errstr);
		
		_LOG_MESSAGE("C","General","Updated RAMQ $ramq");
		return "Updated RAMQ";
	}

	_LOG_MESSAGE("D","General","Ramq $ramq wasn't expired");
	return "RAMQ wasn't expired";
}

#set up error logging functionality
#initialize logging function
sub _LOG_MESSAGE
{
	my $identifier = $_[0];
	my $type = $_[1];
	my $message = $_[2];

	my ($package,$filename,$line) = caller;

	$filename =~ s{.*/}{}; #remove path from the filename

	my $LOG_TABLE = "KioskLog";

	my $now = localtime;
	$now = $now->strftime('%Y-%m-%d %H:%M:%S');	

	#-----------------------------------------------------
	# connect to database and log message
	#-----------------------------------------------------
	my $dbh = LoadConfigs::GetDatabaseConnection("LOGS") or die("Couldn't connect to database");

	my $sql = "
		INSERT INTO $LOG_TABLE (DateTime,FileName,Identifier,Type,Message)
		VALUES (?,?,?,?,?)";

	my $query = $dbh->do($sql,undef,($now,$filename,$identifier,$type,$message)) or die("Query could not be prepared: ".$dbh->errstr);

	$dbh->disconnect;
}

1;
