<?php

declare(strict_types=1);

require __DIR__."/../../../../../../vendor/autoload.php";

use Orms\ApplicationException;
use Orms\Http;
use Orms\Sms\SmsAppointmentInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs('v1');
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$smsAppointment = new class(
    id:         $fields["id"],
    active:     $fields["active"],
    type:       $fields["type"]
) {
    public function __construct(
        public int $id,
        public int $active,
        public ?string $type
    ) {}
};

try {
    SmsAppointmentInterface::updateSmsAppointment($smsAppointment->id, $smsAppointment->active, $smsAppointment->type);
}
catch(ApplicationException $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

Http::generateResponseJsonAndExit(200);
