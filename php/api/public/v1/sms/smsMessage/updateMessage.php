<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../../../../../vendor/autoload.php";

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
