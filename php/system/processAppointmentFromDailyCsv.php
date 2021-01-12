<?php declare(strict_types=1);
#---------------------------------------------------------------------------------------------------------------
# Script that parses a csv file generated from Impromptu containing Medivisit appointment information and inserts/updates the appointment in the ORMS database
#---------------------------------------------------------------------------------------------------------------

#load global configs
require __DIR__."/../../vendor/autoload.php";

use GuzzleHttp\Client;

use Orms\Config;
use Orms\Util\Csv;

#get csv file name from command line arguments
$csvFile = getopt("",["file:"])["file"] ?: "";

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

$appointments = Csv::loadCsvFromFile($csvFile);

if($appointments === []) exit("Error opening file");

#call the importer script and export appointments
$url = Config::getConfigs("path")["BASE_URL"]."/php/system/processAppointmentFromMedivisit";

$client = new Client();

foreach($appointments as $app)
{
    $client->request("POST",$url,[
        "form_params" => $app
    ]);
}

?>
