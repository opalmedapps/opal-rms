<?php declare(strict_types = 1);

require __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Sms\SmsAppointmentInterface;
use Orms\Util\Encoding;

try {
    Http::parseApiInputs();
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$smsAppointments = SmsAppointmentInterface::getAppointmentsForSms();

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($smsAppointments));
