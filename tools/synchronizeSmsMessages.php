<?php declare(strict_types = 1);

/*
    This script takes in an input csv file and updates the SmsMessages db table to match it's contents. It also exports the updated table to a csv file (to account for new codes that have been added).
*/

require_once __DIR__ ."/../vendor/autoload.php";

use Orms\Config;
use Orms\Util\Csv;

#load csv file from command line input

#get csv file name from command line arguments and load it
$csvFile = (getopt("",["file:"]))["file"] ?: "";

if($fileHandle === FALSE) exit("Error opening file");

$combinations = Csv::processCsvFile($fileHandle);




?>
