<?php declare(strict_types = 1);

require __DIR__."/../../../../../../vendor/autoload.php";

use Orms\Http;
use Orms\Sms\SmsAppointmentInterface;
use Orms\Util\Encoding;

try {
    $fields = Http::parseApiInputs();
    $fields = Encoding::utf8_decode_recursive($fields);
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400,error: Http::generateApiParseError($e));
}

$smsMessage = new class(
    message:    $fields["smsMessage"],
    messageId:  $fields["messageId"]
) {
    public function __construct(
        public string $message,
        public int $messageId
    ) {}
};

SmsAppointmentInterface::updateMessageForSms($smsMessage->messageId, $smsMessage->message);

Http::generateResponseJsonAndExit(200);
