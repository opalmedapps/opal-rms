<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a csv file generated from Impromptu containing Medivisit appointment information and inserts/updates the appointment in the ORMS database
#---------------------------------------------------------------------------------------------------------------

#load global configs
require __DIR__."/../../vendor/autoload.php";

use Orms\Config;
use Orms\Util\Csv;

#get csv file name from command line arguments
$csvFile = (getopt("",["file:"]))["file"] ?: "";

#csv file must have been created today, otherwise send an error
#however, if its the weekend, don't send an error
$modDate = (new DateTime())->setTimestamp(filemtime($csvFile) ?: 0)->format("Y-m-d");
$today = (new DateTime())->format("Y-m-d");

if($modDate !== $today)
{
    if(date('D') == 'Sat' || date('D') == 'Sun') {
        exit;
    }
    else {
        throw new Exception("CSV file was not updated today");
    }
}

$appointments = Csv::processCsvFile($csvFile);

if($appointments === []) exit("Error opening file");

#call the importer script and export appointments
$url = Config::getConfigs("path")["BASE_URL"]."/php/system/processAppointmentFromMedivisit";

$ch = curl_init();
foreach($appointments as $app)
{
    curl_setopt_array($ch,[
        CURLOPT_URL             => $url,
        CURLOPT_POSTFIELDS      => $app,
        CURLOPT_RETURNTRANSFER  => TRUE
    ]);
    curl_exec($ch);
}
curl_close($ch);

?>
