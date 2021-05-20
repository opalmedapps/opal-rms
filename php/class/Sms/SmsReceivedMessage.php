<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;

class SmsReceivedMessage
{
    function __construct(
        public string $messageId,
        public string $body,
        public string $clientNumber,
        public string $serviceNumber,
        public DateTime $timeReceived,
        public string $provider
    ) {}
}
