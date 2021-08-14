<?php

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

$params = Http::getRequestContents();

$phoneNumber = $params["phoneNumber"] ?? null;

//phone number must be exactly 10 digits
if($phoneNumber === null || !preg_match("/[0-9]{10}/", $phoneNumber)) {
    Http::generateResponseJsonAndExit(400, error: "Invalid phone number");
}

$patients = PatientInterface::unregisterPhoneNumberFromPatients($phoneNumber);

$patients = array_map(fn($x) => "$x->lastName, $x->firstName", $patients);

$responseString = "Removed number for: ";
$responseString .= implode(" | ", $patients);

Http::generateResponseJsonAndExit(200, $responseString);
