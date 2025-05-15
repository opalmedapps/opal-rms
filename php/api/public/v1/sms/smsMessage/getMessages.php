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
