<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Sms\Internal;

use DateTime;

class SmsReceivedMessage
{
    public function __construct(
        public string $messageId,
        public string $body,
        public string $clientNumber,
        public string $serviceNumber,
        public DateTime $timeReceived,
        public string $provider
    ) {}
}
