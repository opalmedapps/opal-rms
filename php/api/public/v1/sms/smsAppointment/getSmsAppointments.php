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
    Http::parseApiInputs('v1');
}
catch(\Exception $e) {
    Http::generateResponseJsonAndExit(400, error: Http::generateApiParseError($e));
}

$smsAppointments = SmsAppointmentInterface::getAppointmentsForSms();

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($smsAppointments));
