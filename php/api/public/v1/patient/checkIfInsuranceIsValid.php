<?php declare(strict_types=1);

//checks if a patient with an mrn + site combination exists in the database and return json true or false

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Patient\PatientInterface;

try {
    $fields = Http::parseApiInputs();
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$insurance = new class(
    insuranceNumber:  $fields["insuranceNumber"],
    type:             $fields["type"],
) {
    function __construct(
        public string $insuranceNumber,
        public string $type
    ) {}
};

$valid = PatientInterface::isInsuranceValid($insurance->insuranceNumber,$insurance->type);

Http::generateResponseJsonAndExit(200,data: $valid);
