<?php
#---------------------------------------------------------------------------------------------------------------
# Script that parses a csv file generated from Impromptu containing Medivisit appointment information and inserts/updates the appointment in the ORMS database
#---------------------------------------------------------------------------------------------------------------

#load global configs
include_once("SystemLoader.php");

#get csv file name from command line arguments
$csvFile = (getopt(null,["file:"]))["file"];

#csv file must have been created today, otherwise send an error
#however, if its the weekend, don't send an error
// $modDate = (new DateTime())->setTimestamp(filemtime($csvFile))->format("Y-m-d");
// $today = (new DateTime())->format("Y-m-d");

// if($modDate !== $today)
// {
//     if(date('D') == 'Sat' || date('D') == 'Sun') {
//         exit;
//     }
//     else {
//         throw new Exception("CSV file was not updated today");
//     }
// }

$fileHandle = fopen($csvFile,"r");

if($fileHandle === FALSE) exit("Error opening file");

$appointments = processCsvFile($fileHandle);

foreach($appointments as $key => $val) {
    $appointments[$key] = !empty($val) ? $val : NULL;
}

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

exit;

###################################
# Functions
###################################

#takes a just opened file handle for a csv file and inserts all the appointments within into the ORMS db
function processCsvFile($handle): array #$handle is stream
{
    $data = [];

    $headers = fgetcsv($handle);

    #csv file is encoded in iso-8859-1 so we need to change it to utf8
    $headers = array_map('utf8_encode',$headers);

    while(($row = fgetcsv($handle)) !== FALSE)
    {
        $row = array_map('utf8_encode',$headers);
        $data[] = array_combine($headers,$row);
    }

    return $data;
}

?>
