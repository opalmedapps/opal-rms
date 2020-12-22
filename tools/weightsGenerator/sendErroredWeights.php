<?php declare(strict_types = 1);

require_once __DIR__ ."/../../php/AutoLoader.php";


#load the tex file and fill it out

$barCodeImg = __DIR__."/noscan.png";
$logoImg = __DIR__."/logo.png";
$lname = "";
$fname = "";
$rvhMrn = "";
$outputWeightFile = "";
$now = str_replace(
    ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
    ["janv","févr","mars","avril","mai","juin","juil","août","sept","oct","nov","déc"],
    (new DateTime())->format("d M Y H:i")
); #convert english month abreviation to french

$latexString = file_get_contents(__DIR__."/texTemplate.tex");

#replace placeholders with actual data
$vars = [];
preg_match_all("/@@@((?!@).)*@@@/",$latexString,$vars);
$vars = array_map(function($x) {
    return preg_replace("/@@@/","",$x);
},$vars[0]);
$vars = array_unique($vars);

foreach($vars as $var)
{
    $latexString = preg_replace("/@@@$var@@@/",$$var,$latexString);
}

#load the list of patients that need to export the document
#the list contains all RVH mrns
$listOfPatients = json_decode(file_get_contents(__DIR__."/listOfPatients.json"),TRUE);
$listOfPatients = array_map(function($x) {
    return ltrim($x,"0");
},$listOfPatients);

$facility = "RV";
$fmuNumber = "FMU-4183";

foreach($listOfPatients as $noZeroesMrn)
{
    $timestamp = 10000 * microtime(TRUE);
    $dir = __DIR__;
    $baseName = "MUHC-$facility-$noZeroesMrn-$fmuNumber^Orms_$timestamp";
    $baseFilename = "$dir/$baseName";

    #create pdf
    file_put_contents("$baseFilename.tex",$latexString);
    shell_exec("xelatex --halt-on-error --interaction=nonstopmode --output-directory=\"$dir\" --jobname=\"$baseName\" \"$baseFilename.tex\" 2>&1");
    shell_exec("xelatex --halt-on-error --interaction=nonstopmode --output-directory=\"$dir\" --jobname=\"$baseName\" \"$baseFilename.tex\" 2>&1");

    #create xml
    $xmlString = createXmlString($noZeroesMrn,$facility,$fmuNumber);
    file_put_contents("$baseFilename.xml",$xmlString);

    #transfer files
    transferFileWithFtpSsl("$baseFilename.pdf","\\Orms\\MUHC-$facility-$noZeroesMrn-$fmuNumber^Orms.001");
    transferFileWithFtpSsl("$baseFilename.xml","\\Orms\\MUHC-$facility-$noZeroesMrn-$fmuNumber^Orms.xml");

    #cleanup
    if(file_exists("$baseFilename.aux")) unlink("$baseFilename.aux");
    if(file_exists("$baseFilename.log")) unlink("$baseFilename.log");
    if(file_exists("$baseFilename.tex")) unlink("$baseFilename.tex");
    if(file_exists("$baseFilename.pdf")) unlink("$baseFilename.pdf");
    if(file_exists("$baseFilename.xml")) unlink("$baseFilename.xml");

    echo "$noZeroesMrn\n";
}

#functions

function createXmlString($noZeroesMrn,$facility,$fmuNumber)
{
    $now = (new Datetime())->format("Y/m/d H:i:s");

    return "
        <IndexInfo>
            <fileCount>1</fileCount>
            <mrn>$noZeroesMrn</mrn>
            <facility>$facility</facility>
            <docType>MU-4183</docType>
            <docDate>$now</docDate>
            <indexingAction>R</indexingAction>
            <externalSystemIds>
                <externalSystemId>
                    <externalSystem>Aria</externalSystem>
                    <externalId>MUHC-$facility-$noZeroesMrn-$fmuNumber^Aria</externalId>
                </externalSystemId>
            </externalSystemIds>
        </IndexInfo>
    ";
}

function transferFileWithFtpSsl($localFileName,$remoteFileName)
{
    $ftpSettings = [
        "host"  => "172.26.188.167",
        "port"  => 21,
        "username"  => "wnetvmap29\\ProdImport",
        "password" => "Importap29"
    ];

    @ $ftpConnection = ftp_ssl_connect($ftpSettings["host"],$ftpSettings["port"]);
    if($ftpConnection === FALSE) throw new \Exception("Connection could not be established");

    @ $loginResult = ftp_login($ftpConnection,$ftpSettings["username"],$ftpSettings["password"]);
    if($loginResult === FALSE) throw new \Exception("Login failed");

    @ $uploadResult = ftp_put($ftpConnection,$remoteFileName,$localFileName);
    if($uploadResult === FALSE) throw new \Exception("File transfer failed");

    ftp_close($ftpConnection);
}

?>
