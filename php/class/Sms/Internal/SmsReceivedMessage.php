<?php

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
