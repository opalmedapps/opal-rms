#!/usr/bin/perl
#----------------------------------------------
# Script to create a pdf that contains a list of weights for a patient and to send that document to Oacis through ATS
#---------------------------------------------

#Since this is going in aria, this is for RVH patients only
#if they patient doesn't have an RVH id, then they aren't aria patients

#----------------------------------------
# import modules
#----------------------------------------
use strict;
use v5.26;
use lib "./";

use CGI qw(:standard);
use CGI::Carp qw(fatalsToBrowser);

use Time::Piece;
use DBI;
use Net::FTP;
use Net::FTPSSL;

use MIME::Base64 qw(decode_base64);
use SVG::Parser;
use SVG::Rasterize;
use Time::HiRes;

#------------------------------------------
# load configs
#------------------------------------------
our $BASEPATH; #base ORMS folder
our $CGI;
our $WRM_DB;
our $WRM_USER;
our $WRM_PASS;
our $sendDocument;

require "loadConfigs.pl";

print $CGI->header('application/json');

#if the setting is off, we exit immediately
if(!$sendDocument)
{
    say "Document sending not enabled";
    exit;
}

#facility = "MG" or "RV" but weights are always for RV
my $facility = "RV";

#get the time of day so that files with unique filenames can be created
my $timestamp = 100000 * Time::HiRes::gettimeofday();

#------------------------------------------
# parse input parameters
#------------------------------------------
my $patientId = url_param("patientId");
my $fname = url_param("firstName");
my $lname = url_param("lastName");
my $svgInfo = param("POSTDATA"); #the svg data was sent using POST method

#handle date/time
my $now = localtime;

my $today = $now->strftime("%Y/%m/%d %H:%M:%S");
$now = $now->strftime("%d %b %Y %H:%M");

#convert ENG months to FR
#since this is going into latex, format french characters now
my @monthsENG = qw(Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec);
my @monthsFR = qw(janv f\\'evr mars avril mai juin juil ao\\^ut sept oct nov d\\'ec);

for(my $i = 0; $i < scalar @monthsENG; $i++)
{
    ($now = $now) =~ s/$monthsENG[$i]/$monthsFR[$i]/;
}

# this was never actually used in prod so the module was removed...
#verify the patient exists in the hospital ADT
#my $patientExists = verifyPatientWithADT->patientExists($patientMrnRVH,$facility,$fname,$lname);

#if($patientExists ne 1)
#{
#    say "Patient not found in ADT!";
#    exit;
#}

#-----------------------------------------------------
# connect to database and get patient weights
#-----------------------------------------------------
my $dbh =  DBI->connect_cached($WRM_DB,$WRM_USER,$WRM_PASS) or die("Couldn't connect to database: ".DBI->errstr);

#get the patient weights
my $sql = "
    SELECT
        Patient.FirstName,
        Patient.LastName,
        Patient.PatientId AS Mrn,
        PatientMeasurement.Date,
        PatientMeasurement.Time,
        PatientMeasurement.Height,
        PatientMeasurement.Weight,
        PatientMeasurement.BSA,
        PatientMeasurement.AppointmentId
    FROM
        (
            SELECT
                PatientMeasurement.Date,
                PatientMeasurement.PatientSer,
                MAX(PatientMeasurement.LastUpdated) AS LU,
                MAX(PatientMeasurement.PatientMeasurementSer) AS PatientMeasurementSer
            FROM
                PatientMeasurement
            WHERE
                PatientMeasurement.PatientSer = $patientId
            GROUP BY
                PatientMeasurement.Date
        ) AS PM
        INNER JOIN PatientMeasurement ON PatientMeasurement.PatientSer = PM.PatientSer
            AND PatientMeasurement.PatientMeasurementSer = PM.PatientMeasurementSer
        INNER JOIN Patient ON Patient.PatientSerNum = PatientMeasurement.PatientSer
    ORDER BY PatientMeasurement.Date";

my $query = $dbh->prepare($sql) or die("Query could not be prepared: ".$dbh->errstr);
$query->execute() or die("Query execution failed: ".$query->errstr);

my $fname;
my $lname;
my $mrnRVH;
my $noZeroesMrn;
my %dates;

while(my $data = $query->fetchrow_hashref)
{
    my %data = %{$data};

    $fname = $data{'FirstName'};
    $lname = $data{'LastName'};
    $mrnRVH = $data{'Mrn'};
    ($noZeroesMrn = $mrnRVH) =~ s/0*(\d+)/$1/; #remove all leading zeroes in the patient id

    $data{'Time'} = Time::Piece->strptime($data{'Time'},"%H:%M:%S");
    $data{'Time'} = $data{'Time'}->strftime("%H:%M");

    %{$dates{$data{'Date'}}} = ('Time' => $data{'Time'}, 'Height' => $data{'Height'}, 'Weight' => $data{'Weight'}, 'BSA' => $data{'BSA'}, 'AppointmentId' => substr($data{'AppointmentId'},4));
}

#exit if the patient has not had any measurements taken
if(!%dates)
{
    say "No weights!";
    exit;
}

#---------------------------------------------------------
# create a table in latex and then convert to pdf
#---------------------------------------------------------
# open the temporary datafile that will hold the .tex file
my $aptsChart = "FMU-4183";

my $outputWeightFile = "$BASEPATH/perl/$mrnRVH\_$aptsChart\_$timestamp";
my $texFile = "$outputWeightFile.tex";
my $pdfFile = "$outputWeightFile.pdf";

open(my $aptOut,">",$texFile) or die("file $texFile does not exist or may has permissions problems");

#disable until we actually decide to add the highcharts image to the document
#first create a png of the highchart sent to us by the webpage
my $svgParser = SVG::Parser->new();
my $rasterizer = SVG::Rasterize->new();

$svgInfo = decode_base64($svgInfo); #the svg data is encoded in base64 so we decode it

#format the highchart graph
$svgInfo =~ s/Highcharts\.com//g; #remove the watermark from the image

#translate eng to fr
$svgInfo =~ s/Weight/Poids/g;
$svgInfo =~ s/Historical Measurement/Mesures Historique/g;

$svgInfo =~ s/data-z-index="(.+?)"//g; #filter out data-z-index or the rasterizer crashes
$svgInfo = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><!DOCTYPE svg >'. $svgInfo; #add xml info


#create a svg object and give it to the rasterizer
my $svgObject = $svgParser->parse($svgInfo);
$rasterizer->rasterize(svg=> $svgObject);

$rasterizer->write(type=> 'png',file_name=> "$outputWeightFile.png");

# Start the tex file
say $aptOut "%Appointments Template for Aria Chart Level Document";
say $aptOut "\\documentclass[12pt]{article} ";
say $aptOut "\\textheight=8.0in ";
say $aptOut "\\textwidth=8.0in ";
say $aptOut "\\topmargin=-1.0in ";
say $aptOut "\\raggedbottom ";
say $aptOut "\\oddsidemargin=-2.0cm ";
say $aptOut "\\evensidemargin=2.0cm ";
say $aptOut "\\usepackage{latexsym}";
say $aptOut "\\usepackage[pdftex]{graphicx}";
say $aptOut "\\usepackage{fancyhdr}";
say $aptOut "\\usepackage{longtable}";
say $aptOut "\\usepackage[table]{xcolor}";
say $aptOut "\\usepackage{eso-pic}";
say $aptOut "\\usepackage{afterpage}";
say $aptOut "\\usepackage{lastpage}";

say $aptOut "
    \\renewcommand{\\headheight}{1.0in}
    \\renewcommand{\\headrulewidth}{2pt}
    \\renewcommand{\\footrulewidth}{1pt}
    \\setlength{\\headwidth}{\\textwidth}
    \\fancyhead[R]{\\large\\textbf{Liste des poids et tailles du patient}}
    \\fancyhead[C]{\\includegraphics[height=0.53in]{$BASEPATH/images/logo.png}\\\\}

    \\fancyfoot[R]{Page \\thepage~de \\pageref{LastPage}} %page number on right
    \\fancyfoot[C]{} %blank central footer

    \\lfoot{\\textbf{FMU-4183 Source :} ARIA(REV 2018/08/24) \\\\
    \\hfill\\\\
    \\footnotesize{Si une version papier de ce document est re\\c{c}ue aux archives, avec ou sans notes manuscrites, en statut pr\\'{e}liminaire ou final, \\textbf{il ne sera pas num\\'{e}ris\\'{e}}.  Les corrections doivent \\^{e}tre faites dans le document pr\\'{e}liminaire ou via l'addendum si le document est final.\\\\
    \\hfill\\\\
    If a printout of this document is received in Medical Records, with or without handwritten notes, whether it is preliminary or final, \\textbf{it will not be scanned}.  Corrections must be done in the preliminary document or via an addendum if the document is final.}
    } %document info on left
    \\pagestyle{fancy}

    \\definecolor{light-gray}{gray}{0.95}
    \\definecolor{dark-gray}{gray}{0.8}
    \\definecolor{light-blue}{rgb}{0,0,0.99}
    \\definecolor{babyblueeyes}{rgb}{0.63, 0.79, 0.95}

    %\\AddToShipoutPicture{\\AtTextUpperLeft{\\textbf{$lname, $fname (RVH-$mrnRVH)}}}";

say $aptOut "
    \\begin{document}
    \\begin{minipage}{0.65\\textwidth}
    \\hspace{-0.22in} Derni\\`{e}re mise \\`{a} jour : \\textbf{\\color{black}{$now}}\\\\
    \\bigskip

    \\vspace{-0.22in}
    \\hspace{-0.22in} Ce document est mis \\`{a} jour automatiquement \\`{a} la suite de la mise \\`{a} jour du poids ou de le taille du patient dans le syst\\`{e}me ORMS-Aria.
    \\end{minipage}
    \\begin{minipage}{0.25\\textwidth}
    \\begin{flushright}
    \\includegraphics[height=0.8in]{$BASEPATH/images/noscan.png}
    \\end{flushright}
    \\end{minipage}

    \\begin{center}
    \\textbf{$lname, $fname (RVH-$mrnRVH)}
    \\newline
    \\includegraphics[height=4.3in]{$outputWeightFile.png}
    \\end{center}
    \\vspace{-0.3in}";

# Prepare the table to hold the data
say $aptOut "
    \\normalsize
    \\rowcolors{1}{light-gray}{dark-gray}
    {\\renewcommand{\\arraystretch}{1.5}%row padding

    \\hfill\\\\
    \\hfill\\\\
    \\hfill\\\\
    \\hfill\\\\

    \\noindent{\\textbf{\\color{red}The medical information provided by this application (Opal/ORMS) is provided as an
    information resource only, and should not be used or relied on for any final diagnostic,
    prescription or treatment purposes. You, the healthcare provider, must validate the
    information prior to making any clinical decision or prescribing any treatment.}}

    \\begin{longtable}
    {
        |p{0.2\\linewidth}
        |p{0.16\\linewidth}
        |p{0.155\\linewidth}
        |p{0.15\\linewidth}
        |p{0.15\\linewidth}
        |p{0.15\\linewidth}
        |
    }
    \\rowcolor{white}\\multicolumn{6}{l}{\\textbf{$lname, $fname (RVH-$mrnRVH)}}\\\\
    \\rowcolor{white}\\multicolumn{6}{l}{\\color{white}{ }}\\\\
    \\hline
    \\rowcolor{babyblueeyes}
        \\textbf{Date et heure de la mesure}
        &\\textbf{Poids}
        &\\textbf{Taille}
        &\\textbf{Surface \\newline Corporelle}
        &\\textbf{Num\\'{e}ro \\newline du RDV}\\\\
    \\hline
    \\endhead
    \\hline
    \\hline";

# Loop through the dates and fill the table

#the first entry should be in bold
my $boldedFirstRow = 0;

foreach my $date (reverse sort keys %dates)
{
    if(!$boldedFirstRow)
    {
        say $aptOut "\\textbf{$date $dates{$date}{'Time'}} & \\textbf{$dates{$date}{'Weight'} kg} & \\textbf{$dates{$date}{'Height'} cm} & \\textbf{$dates{$date}{'BSA'} m\\textsuperscript{2}} & \\textbf{$dates{$date}{'AppointmentId'}} \\\\";
        $boldedFirstRow = 1;
    }
    else
    {
        say $aptOut "$date $dates{$date}{'Time'} & $dates{$date}{'Weight'} kg & $dates{$date}{'Height'} cm & $dates{$date}{'BSA'} m\\textsuperscript{2} & $dates{$date}{'AppointmentId'} \\\\";
    }

    say $aptOut "\\hline";
}

say $aptOut "  \\end{longtable}}";
say $aptOut "\\end{document}";
close $aptOut;

# latex it twice to get the pagination correct
system("pdflatex -output-directory $BASEPATH/perl/ $texFile >/dev/null 2>&1");
system("pdflatex -output-directory $BASEPATH/perl/ $texFile >/dev/null 2>&1");

#---------------------------------------
#create the xml file for ATS
#--------------------------------------
my $outputXMLFile = "$BASEPATH/perl/MUHC-$facility-$noZeroesMrn-$aptsChart^Aria_$timestamp.xml";

open(my $xmlOut,">",$outputXMLFile) or die "Could not open file '$outputXMLFile' $!";
my $xmlText = "
    <IndexInfo>
        <fileCount>1</fileCount>
        <mrn>$noZeroesMrn</mrn>
        <facility>$facility</facility>
        <docType>MU-4183</docType>
        <docDate>$today</docDate>
        <indexingAction>R</indexingAction>
        <externalSystemIds>
            <externalSystemId>
                <externalSystem>Aria</externalSystem>
                <externalId>MUHC-$facility-$noZeroesMrn-$aptsChart^Aria</externalId>
            </externalSystemId>
        </externalSystemIds>
    </IndexInfo>";

print $xmlOut $xmlText;
close $xmlOut;

#----------------------------------------------------
# setup the FTP and transfer files
#----------------------------------------------------

#prod
my $ftpConnection = Net::FTPSSL->new("172.26.188.167",SSL_version => 'SSLv23') or die("Could not create ftp object");
#LOG_MESSAGE('connect_ftp','General',"Connected to prod server for patient $mrnRVH with message: ". $ftpConnection->message);

$ftpConnection->login("wnetvmap29\\ProdImport","Importap29") or die($ftpConnection->last_message);
#LOG_MESSAGE('login_ftp','General',"Logged in to prod server for patient $mrnRVH with message: ". $ftpConnection->message);

$ftpConnection->binary(); #necessary (vital) for some reason

#send the pdf to the ATS server
$ftpConnection->put($pdfFile,"\\Orms\\MUHC-$facility-$noZeroesMrn-$aptsChart^Orms.001");
LOG_MESSAGE('send_pdf','General',"Sent weight pdf for patient $mrnRVH to server with message: ". $ftpConnection->message);

#copy the xml file to the ATS server
$ftpConnection->put($outputXMLFile,"\\Orms\\MUHC-$facility-$noZeroesMrn-$aptsChart^Orms.xml");
LOG_MESSAGE('send_xml','General',"Sent weight xml for patient $mrnRVH to server with message: ". $ftpConnection->message);

#dev
#our $ftpConnection = Net::FTPSSL->new("172.26.188.198") or die("Could not create ftp object");
#$ftpConnection->login("wnetvmap08\\StrmImport","Importap08") or die($ftpConnection->last_message);

#interface engine
#our $ftpConnection = Net::FTP->new("qdxengine.muhc.mcgill.ca","SSL"=> 1) or die("Could not create ftp object");
#$ftpConnection->login("ariarpt","qdxt1X0") or die($ftpConnection->last_message);
#$ftpConnection->put($pdfFile,"MUHC-$facility-$noZeroesMrn-$aptsChart^Orms.001"); for interface engine
#$ftpConnection->put($outputXMLFile,"MUHC-$facility-$noZeroesMrn-$aptsChart^Orms.xml"); for interface engine

#dev interface engine
#our $ftpConnection = Net::FTP->new("172.26.191.134") or die("Could not create ftp object");
#$ftpConnection->login("ariarpt","qdxt1X0") or die($ftpConnection->last_message);

#remove whatever files were created
system("rm $texFile");
system("rm $outputWeightFile.aux");
system("rm $outputWeightFile.log");
system("rm $outputWeightFile.png");
#system("rm $BASEPATH/perl/texput.log");
system("rm $pdfFile");
system("rm $outputXMLFile");

say "Weight documents sent";

exit;
