<?php

declare(strict_types=1);

require __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Sms\SmsAppointmentInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$fields = new class(
    specialityCode: $fields["specialityCode"],
    type:           $fields["type"]
) {
    public function __construct(
        public string $specialityCode,
        public string $type
    ) {}
};

$messages = SmsAppointmentInterface::getSmsAppointmentMessages($fields->specialityCode, $fields->type);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($messages));
