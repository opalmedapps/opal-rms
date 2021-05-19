<?php declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\Patient;

$phoneNumber = $_GET["phoneNumber"] ?? NULL;

//phone number must be exactly 10 digits
if($phoneNumber === NULL || !preg_match("/[0-9]{10}/",$phoneNumber)) {
    Http::generateResponseJsonAndExit(400,error: "Invalid phone number");
}

$patients = Patient::unregisterPhoneNumberFromPatients($phoneNumber);

$patients = array_map(fn($x) => "$x->lastName, $x->firstName",$patients);

$responseString = "Removed number for: ";
$responseString .= implode(" | ",$patients);

Http::generateResponseJsonAndExit(200,$responseString);

?>
